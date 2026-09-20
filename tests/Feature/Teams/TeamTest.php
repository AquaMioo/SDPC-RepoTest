<?php

namespace Tests\Feature\Teams;

use App\Actions\Teams\CreateTeam;
use App\Enums\AgreementStatus;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Enums\UserRole;
use App\Models\Agreement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_teams_index_page_can_be_rendered()
    {
        $user = User::factory()->student()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('teams.index'));

        $response->assertOk();
    }

    /**
     * The client's own screen carries the other side of the work.
     */
    public function test_a_client_sees_the_team_of_a_student_under_contract(): void
    {
        $client = User::factory()->client()->create();
        $student = User::factory()->student()->create();

        Agreement::factory()->create([
            'team_id' => $client->current_team_id,
            'student_id' => $student->id,
            'status' => AgreementStatus::Active,
        ]);

        $this->actingAs($client)
            ->get(route('teams.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCreateTeam', false)
                ->has('collaboratingTeams', 1)
                ->where('collaboratingTeams.0.name', $student->currentTeam->name)
                ->where('collaboratingTeams.0.student', $student->name)
                ->etc());
    }

    /**
     * An application or an invitation is not a working relationship. Team
     * membership is somebody else's to disclose until both sides have signed.
     */
    public function test_an_unsigned_agreement_does_not_expose_a_student_team(): void
    {
        $client = User::factory()->client()->create();
        $student = User::factory()->student()->create();

        Agreement::factory()->create([
            'team_id' => $client->current_team_id,
            'student_id' => $student->id,
            'status' => AgreementStatus::AwaitingSignatures,
        ]);

        $this->actingAs($client)
            ->get(route('teams.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('collaboratingTeams', 0)
                ->etc());
    }

    /**
     * Nobody is offered a second team, because everybody already has one.
     */
    public function test_the_screen_offers_no_way_to_create_a_team(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('teams.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCreateTeam', false)
                ->has('collaboratingTeams', 0)
                ->etc());
    }

    /**
     * One team per account, true by construction rather than by a rule.
     *
     * Registration hands every account exactly one, and that is the team its
     * owner builds with — renameable and invitable from the day it exists. A
     * second was never anything but a duplicate.
     */
    public function test_a_student_is_given_exactly_one_team_at_registration(): void
    {
        $student = User::factory()->student()->create();

        $this->assertCount(1, $student->teams()->get());
        $this->assertTrue($student->ownsTeam($student->currentTeam));
    }

    public function test_a_student_may_not_raise_a_second_team(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->post(route('teams.store'), ['name' => 'Second Group'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('teams', ['name' => 'Second Group']);
        $this->assertCount(1, $student->fresh()->teams()->get());
    }

    /**
     * Four people including the leader, so three invitations.
     */
    public function test_a_team_cannot_be_invited_past_its_seat_limit(): void
    {
        $leader = User::factory()->student()->create();
        $team = Team::factory()->create(['is_personal' => false]);
        $team->members()->attach($leader, ['role' => TeamRole::Owner->value]);
        $leader->switchTeam($team);

        foreach (range(1, Team::MAX_MEMBERS - 1) as $seat) {
            $this->actingAs($leader)
                ->post(route('teams.invitations.store', $team), [
                    'email' => "mate{$seat}@example.com",
                    'role' => TeamRole::LeadProgrammer->value,
                ])
                ->assertSessionHasNoErrors();
        }

        /* The fourth invitation would seat a fifth person. */
        $this->actingAs($leader)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'onetoomany@example.com',
                'role' => TeamRole::LeadProgrammer->value,
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('team_invitations', [
            'email' => 'onetoomany@example.com',
        ]);
    }

    /**
     * A pending invitation holds a seat, or three sent to a team of two would
     * seat five if everybody said yes.
     */
    public function test_pending_invitations_count_against_the_seats(): void
    {
        $leader = User::factory()->student()->create();
        $team = Team::factory()->create(['is_personal' => false]);
        $team->members()->attach($leader, ['role' => TeamRole::Owner->value]);
        $leader->switchTeam($team);

        $this->assertSame(Team::MAX_MEMBERS - 1, $team->remainingSeats());

        $this->actingAs($leader)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'pending@example.com',
                'role' => TeamRole::LeadProgrammer->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Team::MAX_MEMBERS - 2, $team->fresh()->remainingSeats());
    }

    /**
     * A client's team is the business itself: it owns the postings, and
     * ProjectPolicy counts unfinished ones against it. They get exactly one at
     * registration and raise no others.
     */
    public function test_a_client_may_not_raise_a_team(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->post(route('teams.store'), ['name' => 'Second Business'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('teams', ['name' => 'Second Business']);
    }

    /**
     * A client administers no team: the business is named once at sign up.
     */
    public function test_a_client_may_not_rename_their_team(): void
    {
        $client = User::factory()->client()->create();
        $team = $client->currentTeam;

        $this->actingAs($client)
            ->patch(route('teams.update', ['team' => $team->slug]), [
                'name' => 'Renamed Business',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => $team->name,
        ]);
    }

    /**
     * The teams row hides its edit pencil for a client, and the shared auth
     * role is the only thing on the page that lets it.
     *
     * Nothing in the teams payload says this owner may not edit: a client owns
     * the business team outright, so `role` reads "owner" exactly as a student
     * lead's does, and the row drew a pencil that
     * test_a_client_may_not_rename_their_team shows the server refuses. Drop
     * either fact below and the pencil comes back.
     */
    public function test_the_teams_page_names_the_role_the_screen_is_drawing_for(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('teams.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('teams/index')
                ->where('auth.role', UserRole::Client->value)
                ->where('teams.0.role', TeamRole::Owner->value)
            );
    }

    public function test_a_client_may_not_invite_anybody_into_their_team(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->post(route('teams.invitations.store', $client->currentTeam), [
                'email' => 'someone@example.com',
                /* Assignable, so validation passes and the Gate is what refuses. */
                'role' => TeamRole::LeadProgrammer->value,
            ])
            ->assertForbidden();
    }

    public function test_a_client_may_not_delete_their_team(): void
    {
        $client = User::factory()->client()->create();
        $team = $client->currentTeam;

        $this->actingAs($client)
            ->delete(route('teams.destroy', ['team' => $team->slug]))
            ->assertForbidden();

        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    /**
     * The edit screen draws its buttons from these, so they have to agree with
     * the policy — otherwise it offers three things the server refuses.
     */
    public function test_a_client_is_offered_no_team_administration_on_the_screen(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('teams.edit', ['team' => $client->currentTeam->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.canUpdateTeam', false)
                ->where('permissions.canDeleteTeam', false)
                ->where('permissions.canCreateInvitation', false)
                ->where('permissions.canAddMember', false)
                ->etc());
    }

    /**
     * The client module's own work is not team administration and must survive
     * the restriction above.
     */
    public function test_a_client_keeps_the_permissions_their_own_module_needs(): void
    {
        $client = User::factory()->client()->create();
        $team = $client->currentTeam;

        $this->assertTrue($client->hasTeamPermission($team, TeamPermission::ManageProjects));
        $this->assertTrue($client->hasTeamPermission($team, TeamPermission::ManageApplications));
        $this->assertTrue($client->hasTeamPermission($team, TeamPermission::UpdateClientProfile));
    }

    /**
     * A student still runs their own group in full.
     */
    public function test_a_student_still_administers_their_own_team(): void
    {
        $student = User::factory()->student()->create();
        $team = $student->currentTeam;

        $this->assertTrue($student->hasTeamPermission($team, TeamPermission::UpdateTeam));
        $this->assertTrue($student->hasTeamPermission($team, TeamPermission::CreateInvitation));

        $this->actingAs($student)
            ->patch(route('teams.update', ['team' => $team->slug]), ['name' => 'Renamed Group'])
            ->assertRedirect();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Renamed Group']);
    }

    /**
     * Driven through the action rather than the HTTP endpoint.
     *
     * Nobody creates a team over HTTP any more, but CreateTeam still runs for
     * every registration — so the slug rule it carries still has to hold, and
     * "acme-2" over an existing "acme-10" would be a collision on the next
     * sign up rather than a cosmetic slip.
     */
    public function test_team_slug_uses_next_available_suffix()
    {
        $user = User::factory()->student()->create();

        Team::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
        Team::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
        Team::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

        app(CreateTeam::class)->handle($user, 'Acme');

        $this->assertDatabaseHas('teams', [
            'name' => 'Acme',
            'slug' => 'acme-11',
        ]);
    }

    public function test_the_team_edit_page_can_be_rendered()
    {
        $user = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($user)
            ->get(route('teams.edit', $team));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('teams/edit')
                ->where('members.0.role', TeamRole::Owner->value)
                ->where('members.0.role_label', TeamRole::Owner->label()),
            );
    }

    public function test_team_settings_open_as_a_window_over_the_team_page(): void
    {
        $student = User::factory()->student()->create();

        // The screen draws the real Team page behind the window, so it needs
        // the Team page's own payload — the same one teams.index sends.
        $this->actingAs($student)
            ->get(route('teams.edit', $student->currentTeam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('teams/edit')
                ->has('background.teams', 1)
                ->where('background.canCreateTeam', false)
                ->where('background.membership.kind', 'created')
                ->where('background.collaboratingTeams', []),
            );
    }

    public function test_teams_can_be_updated_by_owners()
    {
        $user = User::factory()->student()->create();
        $team = Team::factory()->create(['name' => 'Original Name']);

        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($user)
            ->patch(route('teams.update', $team), [
                'name' => 'Updated Name',
            ]);

        $response->assertRedirect(route('teams.edit', $team->fresh()));

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_teams_cannot_be_updated_by_members()
    {
        $owner = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($member)
            ->patch(route('teams.update', $team), [
                'name' => 'Updated Name',
            ]);

        $response->assertForbidden();
    }

    public function test_teams_can_be_deleted_by_owners()
    {
        $user = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.destroy', $team), [
                'name' => $team->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('teams', [
            'id' => $team->id,
        ]);
    }

    public function test_team_deletion_requires_name_confirmation()
    {
        $user = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.destroy', $team), [
                'name' => 'Wrong Name',
            ]);

        $response->assertSessionHasErrors('name');

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'deleted_at' => null,
        ]);
    }

    public function test_deleting_current_team_switches_to_alphabetically_first_remaining_team()
    {
        $user = User::factory()->student()->create(['name' => 'Mike']);

        $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
        $zuluTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
        $alphaTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
        $betaTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $user->update(['current_team_id' => $zuluTeam->id]);

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.destroy', $zuluTeam), [
                'name' => $zuluTeam->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('teams', [
            'id' => $zuluTeam->id,
        ]);

        $this->assertEquals($alphaTeam->id, $user->fresh()->current_team_id);
    }

    public function test_deleting_current_team_falls_back_to_personal_team_when_alphabetically_first()
    {
        $user = User::factory()->student()->create();
        $personalTeam = $user->personalTeam();
        $team = Team::factory()->create(['name' => 'Zulu Team']);
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $user->update(['current_team_id' => $team->id]);

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.destroy', $team), [
                'name' => $team->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('teams', [
            'id' => $team->id,
        ]);

        $this->assertEquals($personalTeam->id, $user->fresh()->current_team_id);
    }

    public function test_deleting_non_current_team_leaves_current_team_unchanged()
    {
        $user = User::factory()->student()->create();
        $personalTeam = $user->personalTeam();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $user->update(['current_team_id' => $personalTeam->id]);

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.destroy', $team), [
                'name' => $team->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('teams', [
            'id' => $team->id,
        ]);

        $this->assertEquals($personalTeam->id, $user->fresh()->current_team_id);
    }

    public function test_members_can_leave_non_personal_teams()
    {
        $owner = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($member)
            ->delete(route('teams.leave', $team));

        $response->assertRedirect(route('teams.index'));
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => "You left the team \"{$team->name}\""]);

        $this->assertFalse($member->fresh()->belongsToTeam($team));
    }

    public function test_leaving_current_team_switches_to_alphabetically_first_remaining_team()
    {
        $owner = User::factory()->student()->create();
        $member = User::factory()->create(['name' => 'Mike']);

        $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
        $zuluTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $zuluTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

        $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
        $alphaTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

        $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
        $betaTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

        $member->update(['current_team_id' => $zuluTeam->id]);

        $response = $this
            ->actingAs($member)
            ->delete(route('teams.leave', $zuluTeam));

        $response->assertRedirect(route('teams.index'));

        $this->assertFalse($member->fresh()->belongsToTeam($zuluTeam));
        $this->assertEquals($alphaTeam->id, $member->fresh()->current_team_id);
    }

    public function test_personal_teams_cannot_be_left()
    {
        $user = User::factory()->student()->create();
        $personalTeam = $user->personalTeam();

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.leave', $personalTeam));

        $response->assertForbidden();

        $this->assertTrue($user->fresh()->belongsToTeam($personalTeam));
    }

    public function test_team_owners_cannot_leave_their_team()
    {
        $owner = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('teams.leave', $team));

        $response->assertForbidden();

        $this->assertTrue($owner->fresh()->belongsToTeam($team));
    }

    public function test_users_cannot_leave_teams_they_dont_belong_to()
    {
        $user = User::factory()->student()->create();
        $team = Team::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.leave', $team));

        $response->assertForbidden();
    }

    public function test_deleting_team_switches_other_affected_users_to_their_personal_team()
    {
        $owner = User::factory()->student()->create();
        $member = User::factory()->student()->create();

        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $owner->update(['current_team_id' => $team->id]);
        $member->update(['current_team_id' => $team->id]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('teams.destroy', $team), [
                'name' => $team->name,
            ]);

        $response->assertRedirect();

        $this->assertEquals($member->personalTeam()->id, $member->fresh()->current_team_id);
    }

    public function test_personal_teams_cannot_be_deleted()
    {
        $user = User::factory()->student()->create();

        $personalTeam = $user->personalTeam();

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.destroy', $personalTeam), [
                'name' => $personalTeam->name,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('teams', [
            'id' => $personalTeam->id,
            'deleted_at' => null,
        ]);
    }

    public function test_teams_cannot_be_deleted_by_non_owners()
    {
        $owner = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($member)
            ->delete(route('teams.destroy', $team), [
                'name' => $team->name,
            ]);

        $response->assertForbidden();
    }

    public function test_users_can_switch_teams()
    {
        $user = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($user)
            ->post(route('teams.switch', $team));

        $response->assertRedirect();

        $this->assertEquals($team->id, $user->fresh()->current_team_id);
    }

    public function test_users_cannot_switch_to_team_they_dont_belong_to()
    {
        $user = User::factory()->student()->create();
        $team = Team::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('teams.switch', $team));

        $response->assertForbidden();
    }

    public function test_guests_cannot_access_teams()
    {
        $response = $this->get(route('teams.index'));

        $response->assertRedirect(route('login'));
    }
}
