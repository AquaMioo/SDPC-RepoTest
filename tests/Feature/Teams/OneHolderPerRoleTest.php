<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A team hands each job title out once.
 *
 * Four titles — Project Manager, Quality Assurance, System Analyst, Lead
 * Programmer — and a team of at most four, the leader included. Testers asked
 * for this on 2026-09-23: a group with two Project Managers and no analyst is
 * not a capstone team, and nothing on the screen stopped it.
 */
class OneHolderPerRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_role_somebody_already_holds_cannot_be_invited_again(): void
    {
        Notification::fake();

        [$leader, $team] = $this->teamLedBy();

        $held = User::factory()->student()->create();
        $team->members()->attach($held, ['role' => TeamRole::QualityAssurance->value]);

        $invitee = User::factory()->student()->create();

        $this->actingAs($leader)
            ->post(route('teams.invitations.store', $team), [
                'email' => $invitee->email,
                'role' => TeamRole::QualityAssurance->value,
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('team_invitations', ['email' => $invitee->email]);
    }

    public function test_an_invitation_still_waiting_holds_its_role(): void
    {
        Notification::fake();

        [$leader, $team] = $this->teamLedBy();

        $first = User::factory()->student()->create();
        $second = User::factory()->student()->create();

        $this->actingAs($leader)
            ->post(route('teams.invitations.store', $team), [
                'email' => $first->email,
                'role' => TeamRole::SystemAnalyst->value,
            ])
            ->assertSessionHasNoErrors();

        /* The seat is promised and the title goes with it. */
        $this->actingAs($leader)
            ->post(route('teams.invitations.store', $team), [
                'email' => $second->email,
                'role' => TeamRole::SystemAnalyst->value,
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_an_expired_invitation_releases_its_role(): void
    {
        Notification::fake();

        [$leader, $team] = $this->teamLedBy();

        TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'lapsed@example.com',
            'role' => TeamRole::ProjectManager,
            'invited_by' => $leader->id,
            'expires_at' => now()->subDay(),
        ]);

        $invitee = User::factory()->student()->create();

        $this->actingAs($leader)
            ->post(route('teams.invitations.store', $team), [
                'email' => $invitee->email,
                'role' => TeamRole::ProjectManager->value,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_a_member_cannot_be_moved_onto_a_role_somebody_else_holds(): void
    {
        [$leader, $team] = $this->teamLedBy();

        $analyst = User::factory()->student()->create();
        $programmer = User::factory()->student()->create();

        $team->members()->attach($analyst, ['role' => TeamRole::SystemAnalyst->value]);
        $team->members()->attach($programmer, ['role' => TeamRole::LeadProgrammer->value]);

        $this->actingAs($leader)
            ->patch(route('teams.members.update', ['team' => $team->slug, 'user' => $programmer->id]), [
                'role' => TeamRole::SystemAnalyst->value,
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(
            TeamRole::LeadProgrammer,
            $team->memberships()->where('user_id', $programmer->id)->sole()->role,
        );
    }

    public function test_a_member_may_be_left_on_the_role_they_already_hold(): void
    {
        [$leader, $team] = $this->teamLedBy();

        $analyst = User::factory()->student()->create();
        $team->members()->attach($analyst, ['role' => TeamRole::SystemAnalyst->value]);

        /* Their own title is not a clash with themselves. */
        $this->actingAs($leader)
            ->patch(route('teams.members.update', ['team' => $team->slug, 'user' => $analyst->id]), [
                'role' => TeamRole::SystemAnalyst->value,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_the_team_screen_says_which_roles_are_gone(): void
    {
        Notification::fake();

        [$leader, $team] = $this->teamLedBy();

        $held = User::factory()->student()->create();
        $team->members()->attach($held, ['role' => TeamRole::QualityAssurance->value]);

        TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'waiting@example.com',
            'role' => TeamRole::ProjectManager,
            'invited_by' => $leader->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($leader)
            ->get(route('teams.edit', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->component('teams/edit')
                ->where('takenRoles', [
                    TeamRole::QualityAssurance->value,
                    TeamRole::ProjectManager->value,
                ])
                ->etc());
    }

    /**
     * A student leading a team of their own.
     *
     * @return array{0: User, 1: Team}
     */
    private function teamLedBy(): array
    {
        $leader = User::factory()->student()->create();
        $team = $leader->currentTeam;

        return [$leader, $team];
    }
}
