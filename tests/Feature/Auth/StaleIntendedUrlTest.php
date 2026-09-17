<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Auth\Concerns\CompletesRegistration;
use Tests\TestCase;

/**
 * Where somebody lands after signing up or signing in, when the browser last
 * asked for a page that belonged to a different account.
 *
 * Laravel remembers the page a guest was turned away from and sends them back
 * to it after authenticating. That is right for the same person; it is a 403
 * for anybody else, because every team-scoped URL carries its team's slug.
 */
class StaleIntendedUrlTest extends TestCase
{
    use CompletesRegistration, RefreshDatabase;

    public function test_signing_up_does_not_land_on_another_teams_page(): void
    {
        $client = User::factory()->client()->create();
        Project::factory()->create(['team_id' => $client->current_team_id]);

        // A guest opens a page that belongs to somebody else's team.
        $this->get(route('messages.index', ['current_team' => $client->currentTeam]))
            ->assertRedirect(route('login'));

        $response = $this->completeRegistration([
            'first_name' => 'New',
            'last_name' => 'Student',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Student->value,
            'school_email' => '02000123456@sti.edu.ph',
            'terms' => '1',
        ]);

        $target = $response->headers->get('Location');

        $this->get($target)->assertStatus(200);
    }

    public function test_signing_in_does_not_land_on_another_teams_page(): void
    {
        $owner = User::factory()->client()->create();
        $someoneElse = User::factory()->student()->create();

        $this->get(route('messages.index', ['current_team' => $owner->currentTeam]))
            ->assertRedirect(route('login'));

        $response = $this->post(route('login.store'), [
            'email' => $someoneElse->email,
            'password' => 'password',
        ]);

        $this->get($response->headers->get('Location'))->assertStatus(200);
    }

    /**
     * The same person still goes back to where they were. That is the normal
     * case and the reason the remembered URL exists at all.
     */
    public function test_signing_in_still_returns_you_to_your_own_page(): void
    {
        $client = User::factory()->client()->create();
        $messages = route('messages.index', ['current_team' => $client->currentTeam]);

        $this->get($messages)->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect($messages);
    }

    /**
     * Membership is not the only gate: a client-only page is still a 403 for a
     * student, even on a route they could otherwise address.
     */
    public function test_a_page_behind_another_roles_gate_is_not_honoured(): void
    {
        $student = User::factory()->student()->create();
        $recruit = route('recruit.index', ['current_team' => $student->currentTeam]);

        $this->get($recruit)->assertRedirect(route('login'));

        $response = $this->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $this->assertNotSame($recruit, $response->headers->get('Location'));
        $this->get($response->headers->get('Location'))->assertStatus(200);
    }

    /**
     * A refused URL is used up, not kept for the next person to sign in.
     */
    public function test_a_refused_page_is_forgotten(): void
    {
        $owner = User::factory()->client()->create();
        $someoneElse = User::factory()->student()->create();

        $this->get(route('messages.index', ['current_team' => $owner->currentTeam]));

        $this->post(route('login.store'), [
            'email' => $someoneElse->email,
            'password' => 'password',
        ]);

        $this->assertNull(session('url.intended'));
    }
}
