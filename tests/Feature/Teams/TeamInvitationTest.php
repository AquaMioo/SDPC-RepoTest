<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_invitations_can_be_created()
    {
        Notification::fake();

        $owner = User::factory()->student()->create();
        User::factory()->student()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'invited@example.com',
                'role' => TeamRole::LeadProgrammer->value,
            ]);

        $response->assertRedirect(route('teams.edit', $team));

        $this->assertDatabaseHas('team_invitations', [
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'role' => TeamRole::LeadProgrammer->value,
        ]);
    }

    public function test_inviting_somebody_who_already_has_an_account_reaches_their_bell()
    {
        Notification::fake();

        $owner = User::factory()->student()->create();
        $invited = User::factory()->student()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $this->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => $invited->email,
                'role' => TeamRole::LeadProgrammer->value,
            ]);

        Notification::assertSentTo(
            $invited,
            TeamInvitationNotification::class,
            fn (TeamInvitationNotification $notification): bool => $notification->via($invited) === ['mail', 'database'],
        );
    }

    public function test_an_address_with_no_account_cannot_be_invited()
    {
        Notification::fake();

        $owner = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        /*
         * Invitations used to go out to any address, mailing whoever owned it
         * something they could only act on by registering first — and a team
         * lead who mistyped an address was told "Invitation sent" and then
         * waited on somebody who was never coming (testers, 2026-09-23). Only
         * students who are already on the platform can be invited now.
         */
        $this->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'nobody@example.com',
                'role' => TeamRole::LeadProgrammer->value,
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('team_invitations', ['email' => 'nobody@example.com']);

        Notification::assertNothingSent();
    }

    public function test_a_client_account_cannot_be_invited_to_a_student_team()
    {
        Notification::fake();

        $owner = User::factory()->student()->create();
        $client = User::factory()->client()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $this->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => $client->email,
                'role' => TeamRole::LeadProgrammer->value,
            ])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_invitation_email_for_existing_users_uses_login_route()
    {
        $owner = User::factory()->student()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => $invitedUser->email,
            'invited_by' => $owner->id,
        ]);

        $mail = (new TeamInvitationNotification($invitation))->toMail($invitedUser);

        $this->assertSame(route('login', ['invitation' => $invitation->code]), $mail->actionUrl);
        $this->assertStringContainsString('dashboard', implode(' ', $mail->introLines));
    }

    public function test_invitation_email_for_unknown_users_uses_login_route()
    {
        $owner = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'unknown@example.com',
            'invited_by' => $owner->id,
        ]);

        $mail = (new TeamInvitationNotification($invitation))->toMail((object) []);

        $this->assertSame(route('login', ['invitation' => $invitation->code]), $mail->actionUrl);
        $this->assertStringContainsString('log in', strtolower(implode(' ', $mail->introLines)));
    }

    public function test_team_invitations_can_be_created_by_admins()
    {
        Notification::fake();

        $owner = User::factory()->student()->create();
        $admin = User::factory()->student()->create();
        User::factory()->student()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

        $response = $this
            ->actingAs($admin)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'invited@example.com',
                'role' => TeamRole::LeadProgrammer->value,
            ]);

        $response->assertRedirect(route('teams.edit', $team));
    }

    public function test_existing_team_members_cannot_be_invited()
    {
        Notification::fake();

        $owner = User::factory()->student()->create();
        $member = User::factory()->student()->create(['email' => 'member@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'member@example.com',
                'role' => TeamRole::LeadProgrammer->value,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_duplicate_invitations_cannot_be_created()
    {
        Notification::fake();

        $owner = User::factory()->student()->create();
        User::factory()->student()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'invited@example.com',
                'role' => TeamRole::LeadProgrammer->value,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_team_invitations_cannot_be_created_by_members()
    {
        $owner = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        User::factory()->student()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($member)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'invited@example.com',
                'role' => TeamRole::LeadProgrammer->value,
            ]);

        $response->assertForbidden();
    }

    /**
     * The four job titles are the whole of what a team can hand out.
     */
    public function test_only_the_four_job_titles_are_offered_as_roles()
    {
        $this->assertSame(
            [
                'project_manager',
                'quality_assurance',
                'system_analyst',
                'lead_programmer',
            ],
            array_column(TeamRole::assignable(), 'value'),
        );

        $this->assertSame(
            [
                'Project Manager',
                'Quality Assurance',
                'System Analyst',
                'Lead Programmer',
            ],
            array_column(TeamRole::assignable(), 'label'),
        );
    }

    /**
     * Owner is held by whoever made the team, and the two legacy values are on
     * their way out; none of the three may be invited into.
     */
    public function test_owner_and_the_legacy_roles_cannot_be_invited_into()
    {
        $owner = User::factory()->student()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        foreach ([TeamRole::Owner, TeamRole::Admin, TeamRole::Member] as $rejected) {
            $this
                ->actingAs($owner)
                ->post(route('teams.invitations.store', $team), [
                    'email' => "{$rejected->value}@example.com",
                    'role' => $rejected->value,
                ])
                ->assertSessionHasErrors('role');

            $this->assertDatabaseMissing('team_invitations', [
                'team_id' => $team->id,
                'role' => $rejected->value,
            ]);
        }
    }

    public function test_team_invitations_can_be_cancelled_by_owners()
    {
        $owner = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('teams.invitations.destroy', [$team, $invitation]));

        $response->assertRedirect(route('teams.edit', $team));

        $this->assertDatabaseMissing('team_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_team_invitations_can_be_accepted()
    {
        $owner = User::factory()->student()->create();
        $invitedUser = User::factory()->student()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create(['name' => 'Capstone Crew']);

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'role' => TeamRole::Member,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->post(route('invitations.accept', $invitation));

        $response->assertRedirect(route('dashboard', ['current_team' => $team->slug]));
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'You joined Capstone Crew. It is your team now.']);

        $this->assertTrue($invitedUser->fresh()->belongsToTeam($team));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    /**
     * An invitation can outlive the room it was sent for.
     *
     * The cap is checked again when the seat is actually taken, not only when
     * the invitation was written — otherwise a team that filled up in between
     * seats one person too many.
     */
    public function test_an_invitation_to_a_full_team_can_no_longer_be_accepted()
    {
        $owner = User::factory()->student()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create(['is_personal' => false]);

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        /* The team fills after the invitation went out. */
        foreach (range(1, Team::MAX_MEMBERS - 1) as $ignored) {
            $team->members()->attach(
                User::factory()->student()->create(),
                ['role' => TeamRole::LeadProgrammer->value],
            );
        }

        $this->actingAs($invitedUser)
            ->post(route('invitations.accept', $invitation))
            ->assertSessionHasErrors('invitation');

        $this->assertFalse($invitedUser->fresh()->belongsToTeam($team));
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_team_invitations_can_be_declined_by_the_invited_user()
    {
        $owner = User::factory()->student()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('team_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_team_invitations_cannot_be_declined_by_uninvited_user()
    {
        $owner = User::factory()->student()->create();
        $uninvitedUser = User::factory()->create(['email' => 'uninvited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($uninvitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_accepted_team_invitations_cannot_be_declined()
    {
        $owner = User::factory()->student()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->accepted()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_team_invitations_cannot_be_accepted_by_uninvited_user()
    {
        $owner = User::factory()->student()->create();
        $uninvitedUser = User::factory()->create(['email' => 'uninvited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($uninvitedUser)
            ->post(route('invitations.accept', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertFalse($uninvitedUser->fresh()->belongsToTeam($team));
    }

    public function test_expired_invitations_cannot_be_accepted()
    {
        $owner = User::factory()->student()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->post(route('invitations.accept', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertFalse($invitedUser->fresh()->belongsToTeam($team));
    }
}
