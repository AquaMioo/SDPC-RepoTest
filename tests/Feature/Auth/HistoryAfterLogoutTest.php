<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Signing out has to take the browser's copy of the pages with it.
 *
 * Inertia keeps each visited page's props in the window's history state so
 * Back is instant, and logging out did not touch it. From the login screen you
 * were returned to, pressing Forward drew the admin dashboard again — its user
 * counts, its account list, its review queue — out of history, without asking
 * the server anything. The session was long gone.
 *
 * Two halves, and both are needed: the encrypted history state is made
 * unreadable by rotating its key, and the document itself is kept out of the
 * back/forward cache by Cache-Control: no-store (AddSecurityHeaders, pinned in
 * SecurityHeadersTest).
 *
 * encryptHistory and clearHistory are page-level flags rather than props, and
 * v3 only emits them when true — so they are read off the page object rather
 * than asserted with where().
 */
class HistoryAfterLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_in_page_encrypts_its_history_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $this->assertArrayHasKey(
                'encryptHistory',
                $page->toArray(),
                'History encryption is off, so anything in the back/forward stack stays readable after signing out.',
            ));
    }

    public function test_logging_out_rotates_the_key_and_makes_the_history_unreadable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect();

        $this->assertGuest();

        /*
         * clearHistory travels on the next Inertia response. Once the browser
         * has it the old key is thrown away, so every page still sitting in
         * history fails to decrypt and Inertia asks the server instead — which
         * is how an unauthenticated Forward lands on the login screen.
         */
        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page) => $this->assertArrayHasKey(
                'clearHistory',
                $page->toArray(),
                'Signing out left the previously visited pages decryptable in history.',
            ));
    }

    public function test_an_administrator_signing_out_clears_it_too(): void
    {
        /*
         * Both portals share Fortify's logout route, so the admin dashboard —
         * the screen this was actually reported against — is covered by the
         * same response class.
         */
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('logout'));

        $this->assertGuest();

        $this->get(route('admin.login'))
            ->assertInertia(fn (AssertableInertia $page) => $this->assertArrayHasKey(
                'clearHistory',
                $page->toArray(),
            ));
    }

    public function test_deleting_your_own_account_clears_it_as_well(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertGuest();

        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page) => $this->assertArrayHasKey(
                'clearHistory',
                $page->toArray(),
            ));
    }
}
