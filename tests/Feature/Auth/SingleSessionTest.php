<?php

namespace Tests\Feature\Auth;

use App\Actions\Notifications\PresentNotification;
use App\Models\User;
use App\Notifications\Auth\AccountAccessBlocked;
use App\Support\AccountSession;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * One account, one device at a time.
 *
 * A test has one session store and one guard, so "another device" is made by
 * parking this device's session data and starting from an empty one — see
 * onDevice(). Everything else goes through the real routes.
 */
class SingleSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Session data of the devices not currently in use, by name.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $parkedDevices = [];

    private string $currentDevice = 'laptop';

    public function test_a_second_device_cannot_sign_in_while_the_account_is_in_use(): void
    {
        $user = User::factory()->create();
        $rememberToken = $user->remember_token;

        $this->signIn($user)->assertRedirect();
        $this->get(route('dashboard'))->assertRedirect();

        $this->onDevice('phone');

        $this->signIn($user)
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', AccountSession::IN_USE);

        $this->assertGuest();

        // Being turned away must not free the account or sign the holder out
        // of "remember me" — logout() would have done both.
        $user->refresh();
        $this->assertTrue($user->isOnline());
        $this->assertSame($rememberToken, $user->remember_token);

        $this->onDevice('laptop');

        $this->get(route('dashboard'))->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_refusal_reason_reaches_the_login_screen(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);
        $this->onDevice('phone');
        $this->signIn($user);

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('warning', AccountSession::IN_USE),
        );
    }

    public function test_trying_again_straight_away_is_still_refused(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);
        $this->onDevice('phone');

        $this->signIn($user)->assertRedirect(route('login'));
        $this->signIn($user)->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_account_holder_is_alerted_once_a_minute_at_most(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->signIn($user);
        $this->onDevice('phone');

        $this->signIn($user, ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0 Safari/537.36']);
        $this->signIn($user);

        Notification::assertSentToTimes($user, AccountAccessBlocked::class, 1);
        Notification::assertSentTo(
            $user,
            AccountAccessBlocked::class,
            fn (AccountAccessBlocked $notification): bool => $notification->device === 'Chrome on Windows',
        );

        $this->travel(61)->seconds();

        $this->signIn($user);

        Notification::assertSentToTimes($user, AccountAccessBlocked::class, 2);
    }

    public function test_a_wrong_password_never_alerts_the_holder(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->signIn($user);
        $this->onDevice('phone');

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

        Notification::assertNothingSentTo($user);
    }

    public function test_the_alert_reads_properly_in_the_bell(): void
    {
        $user = User::factory()->create();

        $user->notify(new AccountAccessBlocked(device: 'Firefox on Android', ipAddress: '203.0.113.9'));

        $row = app(PresentNotification::class)->handle($user->notifications()->firstOrFail(), $user->currentTeam);

        $this->assertSame('Someone tried to sign in to your account', $row['title']);
        $this->assertStringContainsString('Firefox on Android (203.0.113.9)', (string) $row['body']);
        $this->assertSame(route('security.edit'), $row['url']);
        $this->assertSame(PresentNotification::SYSTEM_SENDER, $row['from']);
    }

    public function test_signing_out_frees_the_account_straight_away(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);
        $this->post(route('logout'));

        $this->onDevice('phone');

        $this->signIn($user)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_account_nobody_is_using_can_be_taken_over_and_the_old_device_is_signed_out(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);

        $this->travel(User::PRESENCE_WINDOW_MINUTES + 1)->minutes();

        $this->onDevice('phone');

        $this->signIn($user)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->onDevice('laptop');

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', AccountSession::REPLACED);

        $this->assertGuest();
    }

    public function test_a_replaced_device_cannot_claim_the_account_back_with_the_same_session(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);
        $this->travel(User::PRESENCE_WINDOW_MINUTES + 1)->minutes();

        $this->onDevice('phone');
        $this->signIn($user);
        $this->post(route('logout'));

        // The phone has gone and the account is free, but the laptop was
        // replaced: it must sign in again rather than walk back in.
        $this->onDevice('laptop');

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_heartbeat_keeps_the_account_in_use(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);

        $this->travel(4)->minutes();
        $this->getJson(route('session.heartbeat'))->assertNoContent();

        $this->travel(4)->minutes();

        $this->onDevice('phone');

        $this->signIn($user)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_heartbeat_tells_a_replaced_device_where_to_go(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);
        $this->travel(User::PRESENCE_WINDOW_MINUTES + 1)->minutes();

        $this->onDevice('phone');
        $this->signIn($user);

        $this->onDevice('laptop');

        $this->getJson(route('session.heartbeat'))
            ->assertConflict()
            ->assertJson([
                'message' => AccountSession::REPLACED,
                'redirect' => route('login'),
            ]);

        $this->assertGuest();
    }

    public function test_an_inertia_visit_from_a_refused_device_is_redirected_with_a_303(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);
        $this->travel(User::PRESENCE_WINDOW_MINUTES + 1)->minutes();

        $this->onDevice('phone');
        $this->signIn($user);

        $this->onDevice('laptop');

        $this->put(route('user-password.update'), [], ['X-Inertia' => 'true'])
            ->assertStatus(303)
            ->assertRedirect(route('login'));
    }

    public function test_resetting_the_password_takes_the_account_back(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->signIn($user);

        $this->onDevice('owner');

        $this->post(route('password.email'), ['email' => $user->email]);

        $token = null;

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])->assertRedirect(route('login'));

        $this->signIn($user, password: 'a-new-password')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->onDevice('laptop');

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_two_factor_challenge_is_refused_too(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->signIn($user)->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1']);

        $this->onDevice('phone');

        $this->signIn($user)->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        // A recovery code works once; Fortify replaced the laptop's with a new one.
        $this->post(route('two-factor.login.store'), ['recovery_code' => $user->fresh()->recoveryCodes()[0]])
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', AccountSession::IN_USE);

        $this->assertGuest();
    }

    public function test_google_is_refused_too(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
            'services.google.enabled' => true,
        ]);

        $user = User::factory()->fromGoogle()->create();

        $this->actingAs($user)->get(route('dashboard'));

        $this->onDevice('phone');

        $this->get(route('google.redirect'))->assertRedirectContains('accounts.google.com');

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn((new SocialiteUser)->map([
            'id' => $user->google_id,
            'name' => $user->name,
            'nickname' => null,
            'email' => $user->email,
            'avatar' => null,
        ]));

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', AccountSession::IN_USE);

        $this->assertGuest();
    }

    public function test_an_administrator_is_sent_back_to_the_admin_login(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);

        $this->onDevice('phone');

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('warning', AccountSession::IN_USE);

        $this->get(route('admin.login'))->assertInertia(fn (Assert $page) => $page
            ->component('admin/login')
            ->where('warning', AccountSession::IN_USE),
        );
    }

    public function test_coming_back_to_the_login_screen_does_not_lock_you_out_of_your_own_account(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);
        $this->get(route('dashboard'));

        // The tab was closed; later the same browser opens the login screen,
        // which ends the visit, and the person signs in again.
        $this->get(route('login'))->assertOk();
        $this->assertGuest();

        $this->signIn($user)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_administrator_coming_back_to_the_admin_login_is_not_locked_out(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->get(route('admin.dashboard'));

        $this->get(route('admin.login'))->assertOk();
        $this->assertGuest();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertSessionMissing('warning');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_closing_the_last_tab_frees_the_account_straight_away(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);
        $this->get(route('dashboard'));

        $this->post(route('session.leave'))->assertNoContent();
        $this->assertFalse($user->refresh()->isOnline());

        // No five-minute wait for the next device.
        $this->onDevice('phone');

        $this->signIn($user)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_browser_that_left_is_still_signed_in_when_it_comes_back(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);
        $this->post(route('session.leave'));

        $this->get(route('profile.edit'))->assertOk();
        $this->assertAuthenticatedAs($user);

        // Back in use: a second device is refused again.
        $this->assertTrue($user->refresh()->isOnline());

        $this->onDevice('phone');

        $this->signIn($user)->assertSessionHas('warning', AccountSession::IN_USE);
    }

    public function test_a_replaced_device_cannot_free_the_account_by_leaving(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);
        $this->travel(User::PRESENCE_WINDOW_MINUTES + 1)->minutes();

        $this->onDevice('phone');
        $this->signIn($user)->assertRedirect(route('dashboard'));

        // The laptop was replaced; its "I've left" must not free the phone's hold.
        $this->onDevice('laptop');
        $this->postJson(route('session.leave'))->assertConflict();

        $this->assertTrue($user->refresh()->isOnline());

        $this->onDevice('tablet');

        $this->signIn($user)->assertSessionHas('warning', AccountSession::IN_USE);
    }

    public function test_leaving_needs_a_signed_in_session(): void
    {
        $this->postJson(route('session.leave'))->assertUnauthorized();
    }

    public function test_one_browser_can_still_sign_in_as_two_different_people_in_turn(): void
    {
        $client = User::factory()->client()->create();
        $student = User::factory()->student()->create();

        // Settings carries no team slug, so both accounts can open the same URL.
        $this->actingAs($client)->get(route('profile.edit'))->assertOk();
        $this->actingAs($student)->get(route('profile.edit'))->assertOk();
        $this->actingAs($client)->get(route('profile.edit'))->assertOk();

        $this->assertAuthenticatedAs($client);
    }

    public function test_it_can_be_switched_off(): void
    {
        config(['auth.single_session' => false]);

        $user = User::factory()->create();

        $this->signIn($user);
        $this->onDevice('phone');

        $this->signIn($user)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_token_is_never_shared_with_the_page(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);

        $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page
            ->missing('auth.user.active_session_token'),
        );
    }

    /**
     * Post the login form as the given user from the current device.
     *
     * @param  array<string, string>  $server
     */
    private function signIn(User $user, array $server = [], string $password = 'password'): TestResponse
    {
        return $this->withServerVariables($server)->post(route('login.store'), [
            'email' => $user->email,
            'password' => $password,
        ]);
    }

    /**
     * Carry on as a different browser.
     *
     * Parks the current device's session data under its name, restores the
     * named device's (empty if it has never been used), and drops the cached
     * guard so the next request reads who is signed in from that session.
     */
    private function onDevice(string $name): void
    {
        $session = $this->app['session.store'];

        $this->parkedDevices[$this->currentDevice] = $session->all();

        $session->flush();
        $session->put($this->parkedDevices[$name] ?? []);

        $this->currentDevice = $name;

        $this->app['auth']->forgetGuards();
    }
}
