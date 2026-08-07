<?php

namespace Tests\Feature\Admin;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The two portals share one Fortify guard, so these tests pin down which role
 * may enter through which door — and, critically, that an administrator is not
 * dragged through the team-scoped redirect the client module installs.
 */
class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $this->get(route('admin.login'))->assertOk();
    }

    public function test_admins_can_authenticate_using_the_admin_portal(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);

        // The regression this guards: every Fortify response in this app runs
        // through RedirectsToCurrentTeam, which used to abort(403) for anyone
        // without a team. Administrators have none.
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_an_authenticated_admin_can_open_the_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        // Asserting the redirect target is not enough: the destination sits
        // behind auth + role:admin + whatever the client module appends, and
        // that combination is exactly what the merge put at risk.
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_the_team_prefix_does_not_swallow_the_admin_routes(): void
    {
        // The client module mounts everything on a bare {current_team} prefix,
        // which matches any one segment. Before this was constrained, /admin/*
        // resolved to the team route and every admin page 403'd on team
        // membership instead of reaching the admin controllers.
        $admin = User::factory()->admin()->create();

        foreach (['admin.dashboard', 'admin.overview', 'admin.users.index', 'admin.content', 'admin.issues'] as $name) {
            $this->actingAs($admin)
                ->get(route($name))
                ->assertOk();
        }
    }

    public function test_a_team_can_not_take_a_reserved_slug(): void
    {
        // Created without an explicit slug so the generator actually runs —
        // this is the path a client registration takes.
        $team = Team::create(['name' => 'Admin']);

        $this->assertNotSame('admin', $team->slug);
        $this->assertSame('admin-1', $team->slug);

        $settings = Team::create(['name' => 'Settings']);
        $this->assertSame('settings-1', $settings->slug);

        // Ordinary names are untouched.
        $this->assertSame('zenith-solutions', Team::create(['name' => 'Zenith Solutions'])->slug);
    }

    public function test_non_admins_can_not_open_the_admin_dashboard(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_students_can_not_authenticate_using_the_admin_portal(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->post(route('admin.login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'This account is not authorized to use the Admin Portal.',
        ]);
    }

    public function test_clients_can_not_authenticate_using_the_admin_portal(): void
    {
        $client = User::factory()->client()->create();

        $this->post(route('admin.login.store'), [
            'email' => $client->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_admins_can_not_authenticate_using_the_public_login_screen(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Please login using the Admin Portal.',
        ]);
    }

    public function test_clients_authenticate_into_their_own_team(): void
    {
        $client = User::factory()->client()->create();

        $response = $this->post(route('login.store'), [
            'email' => $client->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($client);
        $response->assertRedirect("/{$client->refresh()->currentTeam->slug}/dashboard");
    }

    public function test_students_authenticate_into_their_own_team(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($student);
        $response->assertRedirect("/{$student->refresh()->currentTeam->slug}/dashboard");
    }

    public function test_admins_can_not_authenticate_with_an_invalid_password(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_deactivated_accounts_can_not_authenticate(): void
    {
        $client = User::factory()->client()->deactivated()->create();

        $response = $this->post(route('login.store'), [
            'email' => $client->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_accounts_without_a_password_can_not_use_the_password_form(): void
    {
        $student = User::factory()->student()->fromGoogle()->create();

        $this->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_the_admin_portal_is_rate_limited(): void
    {
        $admin = User::factory()->admin()->create();

        RateLimiter::increment(
            md5('login'.implode('|', [$admin->email, '127.0.0.1'])),
            amount: 5,
        );

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
