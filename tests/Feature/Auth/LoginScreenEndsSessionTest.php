<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Back out of a portal and the visit is over.
 *
 * Pressing Back on a dashboard asks the browser for the login screen. While
 * the session survived that, Forward walked straight back into the dashboard
 * without asking for anything — which on a shared computer means the previous
 * person's portal is two arrow keys away.
 */
class LoginScreenEndsSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_reaching_the_login_screen_is_signed_out(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.login'))->assertOk();

        $this->assertGuest();
    }

    public function test_forward_cannot_reach_the_admin_dashboard_afterwards(): void
    {
        /*
         * The behaviour the whole change exists for. Back leaves the dashboard
         * for the login screen, which ends the session; Forward asks for the
         * dashboard again and is sent back to type an email and a password.
         */
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.login'));

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_a_client_is_signed_out_and_cannot_go_forward_either(): void
    {
        $user = User::factory()->create();
        $slug = $user->personalTeam()->slug;

        $this->actingAs($user)->get(route('login'))->assertOk();

        $this->assertGuest();

        $this->get(route('dashboard', ['current_team' => $slug]))
            ->assertRedirect(route('login'));
    }

    public function test_a_student_is_signed_out_too(): void
    {
        // Asked for on every portal, so it is asserted on every portal.
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('login'))->assertOk();

        $this->assertGuest();
    }

    public function test_signing_in_discards_the_history_from_before_it(): void
    {
        /*
         * The half that no response header could reach. Inertia rebuilds Back
         * and Forward from encrypted history state without asking the server,
         * so the login screen kept coming back with the session still live and
         * Forward walked straight into the dashboard — verified in a browser,
         * with no request leaving it in either direction.
         *
         * Rotating the key at sign-in makes the entries written before it
         * unreadable, which is what forces that Back to become a real request
         * and reach the middleware above.
         */
        $user = User::factory()->admin()->create();

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertTrue(session('inertia.clear_history'));
    }

    public function test_a_signed_out_visitor_still_just_sees_the_form(): void
    {
        // The ordinary case has to keep working: nothing to end, no redirect.
        $this->get(route('login'))->assertOk();

        $this->assertGuest();
    }
}
