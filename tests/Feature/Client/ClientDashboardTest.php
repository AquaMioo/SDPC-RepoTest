<?php

namespace Tests\Feature\Client;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_can_reach_their_workspace(): void
    {
        $user = User::factory()->client()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('client.dashboard', ['current_team' => $user->currentTeam]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('client/dashboard'));
    }

    public function test_a_client_lands_on_their_workspace_after_logging_in(): void
    {
        $user = User::factory()->client()->create();

        $response = $this
            ->followingRedirects()
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('client/dashboard'));
    }

    public function test_a_student_cannot_reach_the_client_workspace(): void
    {
        $user = User::factory()->student()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('client.dashboard', ['current_team' => $user->currentTeam]));

        $response->assertForbidden();
    }

    public function test_the_workspace_carries_pending_invitations(): void
    {
        $owner = User::factory()->create(['name' => 'Taylor Otwell']);
        $client = User::factory()->client()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create(['name' => 'Laravel Team']);

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('client/dashboard')
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.code', $invitation->code)
            ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
            ->where('pendingInvitations.0.team.name', 'Laravel Team'),
        );
    }

    public function test_the_workspace_ignores_invitations_addressed_to_someone_else(): void
    {
        $owner = User::factory()->create();
        $client = User::factory()->client()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'someone@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('client/dashboard')
            ->has('pendingInvitations', 0),
        );
    }
}
