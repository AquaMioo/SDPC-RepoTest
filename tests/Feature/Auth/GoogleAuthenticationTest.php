<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->get(route('admin.google.redirect'))->assertNotFound();
        $this->get(route('google.callback'))->assertNotFound();
    }

    public function test_the_google_routes_are_closed_to_users_who_are_already_signed_in(): void
    {
        $this->enableGoogle();

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('google.redirect'))->assertRedirect();
        $this->actingAs($user)->get(route('google.callback'))->assertRedirect();
    }

    public function test_a_new_student_is_created_and_sent_to_the_credential_step(): void
    {
        $this->startFlowFrom(route('google.redirect', ['role' => 'student']));
        $this->mockGoogleReturns($this->googleUser());

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('credentials.create'));
        $this->assertAuthenticated();

        $user = User::firstWhere('email', 'ada@example.com');

        $this->assertSame(UserRole::Student, $user->role);
        $this->assertSame('1234567890', $user->google_id);
        $this->assertSame('Ada', $user->first_name);
        $this->assertSame('Lovelace', $user->last_name);
        $this->assertSame('https://example.com/avatar.jpg', $user->avatar);

        // Google has already proven the address, and the account never gets a
        // password, so the password form can not be used to reach it.
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);

        // A student's team is personal and never shown to them, but it has to
        // exist: every route past this point is scoped to one.
        $this->assertNotNull($user->personalTeam());
        $this->assertSame("Ada Lovelace's Team", $user->currentTeam->name);
    }

    public function test_a_new_account_can_be_created_with_the_client_role(): void
    {
        $this->startFlowFrom(route('google.redirect', ['role' => 'client']));
        $this->mockGoogleReturns($this->googleUser());

        $response = $this->get(route('google.callback'));

        $this->assertAuthenticated();

        $user = User::firstWhere('email', 'ada@example.com');

        $this->assertSame(UserRole::Client, $user->role);

        // A client's team is their business rather than a personal one, so it
        // is not flagged personal even though Google gave us no business name.
        $this->assertFalse($user->currentTeam->is_personal);
        $this->assertTrue($user->ownsTeam($user->currentTeam));

        // The team has to exist before this redirect resolves: /dashboard on its
        // own matches no route, only {current_team}/dashboard does.
        $response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
    }

    public function test_a_new_google_account_lands_on_a_page_that_actually_exists(): void
    {
        $this->startFlowFrom(route('google.redirect', ['role' => 'client']));
        $this->mockGoogleReturns($this->googleUser());

        $this->followingRedirects()
            ->get(route('google.callback'))
            ->assertOk();
    }

    public function test_a_tampered_sign_up_role_can_not_create_an_administrator(): void
    {
        $this->startFlowFrom(route('google.redirect', ['role' => 'admin']));
        $this->mockGoogleReturns($this->googleUser());

        $this->get(route('google.callback'));

        $this->assertSame(UserRole::Student, User::firstWhere('email', 'ada@example.com')->role);
    }

    public function test_an_existing_account_is_linked_by_email_and_keeps_its_role(): void
    {
        $client = User::factory()->client()->create(['email' => 'ada@example.com']);

        $this->startFlowFrom(route('google.redirect', ['role' => 'student']));
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
        $response->assertSessionHasErrors('email');
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
        $response->assertSessionHasErrors(['email' => 'Please login using the Admin Portal.']);
        $this->assertGuest();
    }

    public function test_an_administrator_can_sign_in_through_the_admin_portal(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'ada@example.com']);

        $this->startFlowFrom(route('admin.google.redirect'));
        $this->mockGoogleReturns($this->googleUser());

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertSame('1234567890', $admin->refresh()->google_id);
    }

    public function test_a_student_can_not_sign_in_through_the_admin_portal(): void
    {
        User::factory()->student()->create(['email' => 'ada@example.com']);

        $this->startFlowFrom(route('admin.google.redirect'));
        $this->mockGoogleReturns($this->googleUser());

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors(['email' => 'This account is not authorized to use the Admin Portal.']);
        $this->assertGuest();
    }

    public function test_an_unknown_google_account_can_not_register_through_the_admin_portal(): void
    {
        $this->startFlowFrom(route('admin.google.redirect'));
        $this->mockGoogleReturns($this->googleUser());

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_a_google_account_without_an_email_address_is_rejected(): void
    {
        $this->startFlowFrom(route('google.redirect'));
        $this->mockGoogleReturns($this->googleUser(['email' => null]));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
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
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_failure_on_the_admin_portal_returns_to_the_admin_login_screen(): void
    {
        $this->startFlowFrom(route('admin.google.redirect'));

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow(new RuntimeException('Invalid state.'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->get(route('google.callback'))->assertRedirect(route('admin.login'));
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
