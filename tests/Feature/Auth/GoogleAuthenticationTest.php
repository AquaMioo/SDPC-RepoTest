<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_redirect_route_sends_the_user_to_google(): void
    {
        $this->enableGoogle();

        $response = $this->get(route('google.redirect'));

        $response->assertRedirectContains('accounts.google.com');
    }

    public function test_the_google_routes_are_closed_when_credentials_are_not_configured(): void
    {
        config(['services.google.enabled' => false]);

        $this->get(route('google.redirect'))->assertNotFound();
        $this->get(route('google.callback'))->assertNotFound();
    }

    public function test_the_google_routes_are_closed_to_users_who_are_already_signed_in(): void
    {
        $this->enableGoogle();

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('google.redirect'))->assertRedirect();
        $this->actingAs($user)->get(route('google.callback'))->assertRedirect();
    }

    public function test_an_unknown_google_account_can_not_create_an_account(): void
    {
        $this->startFlowFrom(route('google.redirect'));
        $this->mockGoogleReturns($this->googleUser());

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest();

        // Google is a way in, never a way to sign up: registration collects the
        // role, school or business name and the terms, none of which Google
        // knows, so no account may appear out of a Google identity.
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
        $this->assertSame(0, User::count());
    }

    public function test_the_failure_message_reaches_the_login_screen(): void
    {
        $this->startFlowFrom(route('google.redirect'));
        $this->mockGoogleReturns($this->googleUser());

        $this->get(route('google.callback'));

        // The flow returns as a fresh GET rather than a form submission, so the
        // message only shows if it survives into the page's shared error bag.
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where(
                'errors.google',
                'No account is registered for this Google account. Please create an account first.',
            ),
        );
    }

    public function test_an_unknown_google_account_prefills_the_sign_up_form(): void
    {
        $this->startFlowFrom(route('google.redirect', ['intent' => 'register']));
        $this->mockGoogleReturns($this->googleUser());

        $response = $this->get(route('google.callback'));

        // Back to the form, carrying what Google vouched for. No error: there
        // is nothing wrong, there is just more to fill in.
        $response->assertRedirect(route('register'));
        $response->assertSessionHasNoErrors();
        $this->assertGuest();
        $this->assertSame(0, User::count());

        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/register')
            ->where('googleProfile.email', 'ada@example.com')
            ->where('googleProfile.first_name', 'Ada')
            ->where('googleProfile.last_name', 'Lovelace'),
        );
    }

    public function test_a_google_identity_becomes_an_account_once_the_form_is_finished(): void
    {
        $this->startFlowFrom(route('google.redirect', ['intent' => 'register']));
        $this->mockGoogleReturns($this->googleUser());
        $this->get(route('google.callback'));

        $response = $this->post(route('register.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'role' => 'student',
            'school_email' => '02000123456@sti.edu.ph',
            'terms' => 'on',
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::firstWhere('email', 'ada@example.com');

        $this->assertNotNull($user);
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertSame('1234567890', $user->google_id);

        // Google is how they sign in, so there is no password, and the address
        // needs no verification email.
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);

        // Still unverified as an account: the credential document decides that.
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertNotNull($user->currentTeam);

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_address_comes_from_google_rather_than_the_form(): void
    {
        $this->startFlowFrom(route('google.redirect', ['intent' => 'register']));
        $this->mockGoogleReturns($this->googleUser());
        $this->get(route('google.callback'));

        // The email input is read only on screen, but nothing stops a crafted
        // request posting another address. The session wins.
        $this->post(route('register.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'someone.else@example.com',
            'role' => 'client',
            'business_name' => 'Northwind',
            'terms' => 'on',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, User::count());
        $this->assertNotNull(User::firstWhere('email', 'ada@example.com'));
        $this->assertNull(User::firstWhere('email', 'someone.else@example.com'));
    }

    public function test_registering_without_google_still_needs_a_password(): void
    {
        $this->post(route('register.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'role' => 'student',
            'school_email' => '02000123456@sti.edu.ph',
            'terms' => 'on',
        ])->assertSessionHasErrors('password');

        $this->assertSame(0, User::count());
    }

    public function test_an_already_registered_account_can_not_be_registered_again(): void
    {
        $student = User::factory()->student()->create(['email' => 'ada@example.com']);

        $this->startFlowFrom(route('google.redirect', ['intent' => 'register']));
        $this->mockGoogleReturns($this->googleUser());

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'google' => 'This Google account is already registered as a Student. Please log in instead.',
        ]);

        // Pressing sign up does not quietly log them in instead.
        $this->assertGuest();
        $this->assertSame(1, User::count());
        $this->assertNull($student->refresh()->google_id);
    }

    public function test_a_registered_student_can_sign_in_with_google(): void
    {
        $student = User::factory()->student()->create(['email' => 'ada@example.com']);

        $this->startFlowFrom(route('google.redirect'));
        $this->mockGoogleReturns($this->googleUser());

        $response = $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($student);
        $response->assertRedirect(route('dashboard', [
            'current_team' => $student->personalTeam()->slug,
        ]));
    }

    public function test_an_existing_account_is_linked_by_email_and_keeps_its_role(): void
    {
        $client = User::factory()->client()->create(['email' => 'ada@example.com']);

        $this->startFlowFrom(route('google.redirect'));
        $this->mockGoogleReturns($this->googleUser());

        $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($client);
        $this->assertSame(1, User::where('email', 'ada@example.com')->count());

        $client->refresh();

        $this->assertSame('1234567890', $client->google_id);
        $this->assertSame('https://example.com/avatar.jpg', $client->avatar);

        // The role on an existing account wins over whatever the sign up form
        // was toggled to, so a client is never demoted back to a student.
        $this->assertSame(UserRole::Client, $client->role);
    }

    public function test_an_existing_account_is_matched_regardless_of_email_casing(): void
    {
        $client = User::factory()->client()->create(['email' => 'ada@example.com']);

        $this->startFlowFrom(route('google.redirect'));
        $this->mockGoogleReturns($this->googleUser(['email' => 'ADA@Example.com']));

        $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($client);
        $this->assertSame(1, User::count());
    }

    public function test_a_deactivated_account_can_not_sign_in_with_google(): void
    {
        $user = User::factory()->client()->deactivated()->create(['email' => 'ada@example.com']);

        $this->startFlowFrom(route('google.redirect'));
        $this->mockGoogleReturns($this->googleUser());

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest();

        // The identity is not attached to an account that can not use it.
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_an_administrator_can_not_sign_in_through_the_public_portal(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'ada@example.com']);

        $this->startFlowFrom(route('google.redirect'));
        $this->mockGoogleReturns($this->googleUser());

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['google' => 'Please login using the Admin Portal.']);
        $this->assertGuest();
    }

    public function test_the_admin_portal_offers_no_google_route_at_all(): void
    {
        // Administrator accounts are issued by the developers, never
        // self-served, so the portal is password only and the route is gone
        // rather than merely hidden.
        $this->assertFalse(Route::has('admin.google.redirect'));

        $this->enableGoogle();

        $this->get('/admin/auth/google/redirect')->assertNotFound();
    }

    public function test_the_admin_login_screen_offers_no_google_button(): void
    {
        $this->enableGoogle();

        $this->get(route('admin.login'))->assertInertia(fn (Assert $page) => $page
            ->component('admin/login')
            ->missing('canLoginWithGoogle')
            ->missing('googleSetupHint'),
        );
    }

    public function test_a_google_account_without_an_email_address_is_rejected(): void
    {
        $this->startFlowFrom(route('google.redirect'));
        $this->mockGoogleReturns($this->googleUser(['email' => null]));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_a_failure_from_google_returns_to_the_login_screen(): void
    {
        $this->startFlowFrom(route('google.redirect'));

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow(new RuntimeException('Invalid state.'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_the_google_flow_is_rate_limited(): void
    {
        $this->enableGoogle();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->get(route('google.redirect'));
        }

        $this->get(route('google.redirect'))->assertTooManyRequests();
    }

    /**
     * Turn the feature on with throwaway credentials.
     *
     * The real env has none in testing, which leaves services.google.enabled
     * false and every route below answering 404.
     */
    private function enableGoogle(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
            'services.google.enabled' => true,
        ]);
    }

    /**
     * Start the OAuth flow at the given portal's redirect route.
     *
     * The callback reads the originating portal and the chosen sign up role
     * from the session, so the flow has to be entered properly rather than
     * calling the callback cold.
     */
    private function startFlowFrom(string $url): void
    {
        $this->enableGoogle();

        $this->get($url)->assertRedirectContains('accounts.google.com');
    }

    /**
     * Build the identity Google would hand back.
     *
     * @param  array<string, string|null>  $attributes
     */
    private function googleUser(array $attributes = []): SocialiteUser
    {
        return (new SocialiteUser)->map(array_merge([
            'id' => '1234567890',
            'name' => 'Ada Lovelace',
            'nickname' => null,
            'email' => 'ada@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ], $attributes));
    }

    /**
     * Make the callback resolve to the given identity instead of calling Google.
     */
    private function mockGoogleReturns(SocialiteUser $googleUser): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }
}
