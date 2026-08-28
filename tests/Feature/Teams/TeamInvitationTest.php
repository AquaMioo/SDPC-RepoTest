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

        $owner = User::factory()->create();
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

        $owner = User::factory()->create();
        $invited = User::factory()->create(['email' => 'invited@example.com']);
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

    public function test_inviting_an_address_with_no_account_stays_email_only()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $this->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'nobody@example.com',
                'role' => TeamRole::LeadProgrammer->value,
            ]);

        /*
         * There is no account to hang a row on, so the database channel has to
         * drop out — asking for it on an AnonymousNotifiable is what would
         * break the invitation for everybody who has not registered yet.
         */
        Notification::assertSentOnDemand(
            TeamInvitationNotification::class,
            fn (TeamInvitationNotification $notification, array $channels, object $notifiable): bool => $notification->via($notifiable) === ['mail'],
        );
    }

    public function test_invitation_email_for_existing_users_uses_login_route()
    {
        $owner = User::factory()->create();
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
        $owner = User::factory()->create();
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

        $owner = User::factory()->create();
        $admin = User::factory()->create();
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

        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
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

        $owner = User::factory()->create();
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
        $owner = User::factory()->create();
        $member = User::factory()->create();
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
        $owner = User::factory()->create();
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
        $owner = User::factory()->create();
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
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

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

        $response->assertRedirect(route('dashboard'));
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Invitation accepted.']);

        $this->assertTrue($invitedUser->fresh()->belongsToTeam($team));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_team_invitations_can_be_declined_by_the_invited_user()
    {
        $owner = User::factory()->create();
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
        $owner = User::factory()->create();
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
        $owner = User::factory()->create();
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
        $owner = User::factory()->create();
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
        $owner = User::factory()->create();
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
