<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationProvider;
use App\Models\School;
use App\Models\StudentVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use RuntimeException;
use SocialiteProviders\Microsoft\MicrosoftUser;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;
use Tests\TestCase;

/**
 * Students signing up and signing in with their school Microsoft account.
 *
 * The identity is the sign-in name (userPrincipalName), which Microsoft only
 * hands out on domains the school's tenant has proved it owns. Most of these
 * tests are about what is turned away.
 */
class MicrosoftAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_redirect_route_sends_the_student_to_microsoft(): void
    {
        $this->enableMicrosoft();

        $response = $this->get(route('microsoft.redirect'));

        // School and work accounts only, and always asking which one.
        $response->assertRedirectContains('login.microsoftonline.com/organizations/oauth2/v2.0/authorize');
        $response->assertRedirectContains('prompt=select_account');
    }

    public function test_the_microsoft_routes_are_closed_when_credentials_are_not_configured(): void
    {
        config(['services.microsoft.enabled' => false]);

        $this->get(route('microsoft.redirect'))->assertNotFound();
        $this->get(route('microsoft.callback'))->assertNotFound();
    }

    public function test_the_microsoft_routes_are_closed_to_users_who_are_already_signed_in(): void
    {
        $this->enableMicrosoft();

        $user = User::factory()->student()->create();

        $this->actingAs($user)->get(route('microsoft.redirect'))->assertRedirect();
        $this->actingAs($user)->get(route('microsoft.callback'))->assertRedirect();
    }

    public function test_the_admin_portal_has_no_microsoft_route(): void
    {
        $this->assertFalse(Route::has('admin.microsoft.redirect'));
        $this->assertFalse(Route::has('admin.microsoft.callback'));
    }

    public function test_the_login_screen_offers_microsoft_when_it_is_configured(): void
    {
        $this->enableMicrosoft();

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('canLoginWithMicrosoft', true),
        );
    }

    public function test_a_new_school_account_prefills_the_sign_up_form(): void
    {
        $this->startRegistration();

        $response = $this->get(route('microsoft.callback'));

        $response->assertRedirect(route('register'));
        $response->assertSessionHasNoErrors();
        $this->assertGuest();
        $this->assertSame(0, User::count());

        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/register')
            ->where('microsoftProfile.email', '02000123456@sti.edu.ph')
            // Microsoft sends the names apart, so a two-word surname survives.
            ->where('microsoftProfile.first_name', 'Juan')
            ->where('microsoftProfile.last_name', 'Dela Cruz')
            // The id stays on the server.
            ->missing('microsoftProfile.microsoft_id'),
        );
    }

    public function test_a_school_identity_becomes_a_student_account_without_a_code(): void
    {
        Notification::fake();

        $this->startRegistration();
        $this->get(route('microsoft.callback'));

        $this->post(route('register.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'role' => 'student',
            'terms' => 'on',
        ])->assertSessionHasNoErrors();

        $user = User::firstWhere('email', '02000123456@sti.edu.ph');

        $this->assertNotNull($user);
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertSame('ms-object-id-1', $user->microsoft_id);
        $this->assertNull($user->password);
        $this->assertNull($user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);

        // The school's own sign-in proved the address, so nothing was mailed.
        Notification::assertNothingSent();
    }

    public function test_the_address_comes_from_microsoft_rather_than_the_form(): void
    {
        $this->startRegistration();
        $this->get(route('microsoft.callback'));

        $this->post(route('register.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'role' => 'student',
            'school_email' => 'someone.else@sti.edu.ph',
            'email' => 'juan@gmail.com',
            'terms' => 'on',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, User::count());
        $this->assertNotNull(User::firstWhere('email', '02000123456@sti.edu.ph'));
    }

    public function test_a_school_identity_can_not_register_a_client(): void
    {
        $this->startRegistration();
        $this->get(route('microsoft.callback'));

        $this->from(route('register'))->post(route('register.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'role' => 'client',
            'business_name' => 'Dela Cruz Hardware',
            'terms' => 'on',
        ])->assertSessionHasErrors([
            'role' => 'A school Microsoft account can only register a student.',
        ]);

        $this->assertSame(0, User::count());
    }

    public function test_a_listed_school_is_recorded_as_verified_at_sign_up(): void
    {
        School::factory()->create(['domain' => 'sti.edu.ph']);

        $this->startRegistration();
        $this->get(route('microsoft.callback'));

        $this->post(route('register.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'role' => 'student',
            'terms' => 'on',
        ]);

        $verification = StudentVerification::sole();

        $this->assertSame(VerificationProvider::SchoolEmail, $verification->provider);
        $this->assertSame('02000123456@sti.edu.ph', $verification->payload['email']);
    }

    public function test_the_pending_identity_can_be_dropped(): void
    {
        $this->startRegistration();
        $this->get(route('microsoft.callback'));

        $this->delete(route('register.identity.forget'))
            ->assertRedirect(route('register'));

        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
            ->where('microsoftProfile', null)
            ->where('googleProfile', null),
        );
    }

    public function test_an_address_claimed_while_the_form_was_open_is_sent_to_sign_in(): void
    {
        $this->startRegistration();
        $this->get(route('microsoft.callback'));

        User::factory()->student()->create(['email' => '02000123456@sti.edu.ph']);

        $this->post(route('register.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'role' => 'student',
            'terms' => 'on',
        ])->assertRedirect(route('login'));

        $this->assertSame(1, User::count());
        $this->assertGuest();
    }

    public function test_an_already_registered_school_account_can_not_register_again(): void
    {
        User::factory()->student()->create(['email' => '02000123456@sti.edu.ph']);

        $this->startRegistration();

        $response = $this->get(route('microsoft.callback'));

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'microsoft' => 'This Microsoft account is already registered as a Student. Please log in instead.',
        ]);
        $this->assertGuest();
    }

    public function test_a_registered_student_signs_in_and_the_identity_is_linked(): void
    {
        $student = User::factory()->student()->create(['email' => '02000123456@sti.edu.ph']);

        $this->startLogin();

        $response = $this->get(route('microsoft.callback'));

        $this->assertAuthenticatedAs($student);
        $response->assertRedirect(route('dashboard', [
            'current_team' => $student->personalTeam()->slug,
        ]));
        $this->assertSame('ms-object-id-1', $student->refresh()->microsoft_id);
    }

    public function test_a_linked_identity_is_found_even_after_the_address_changes(): void
    {
        $student = User::factory()->student()->create([
            'email' => 'juan.new@sti.edu.ph',
            'microsoft_id' => 'ms-object-id-1',
        ]);

        $this->startLogin();
        $this->get(route('microsoft.callback'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_an_unknown_school_account_is_sent_to_sign_up(): void
    {
        $this->startLogin();

        $response = $this->get(route('microsoft.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'microsoft' => 'No SDPC account uses 02000123456@sti.edu.ph yet. Choose Student on the sign up page and continue with Microsoft there.',
        ]);
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_an_account_linked_to_another_microsoft_identity_is_refused(): void
    {
        $student = User::factory()->student()->create([
            'email' => '02000123456@sti.edu.ph',
            'microsoft_id' => 'ms-object-id-OTHER',
        ]);

        $this->startLogin();
        $this->get(route('microsoft.callback'))->assertSessionHasErrors('microsoft');

        $this->assertGuest();
        $this->assertSame('ms-object-id-OTHER', $student->refresh()->microsoft_id);
    }

    public function test_a_personal_microsoft_account_is_refused(): void
    {
        User::factory()->student()->create(['email' => '02000123456@sti.edu.ph']);

        $this->startLogin(personalAccount: true);

        $this->get(route('microsoft.callback'))->assertSessionHasErrors([
            'microsoft' => 'That is a personal Microsoft account. Sign in with the Microsoft account your school gave you.',
        ]);
        $this->assertGuest();
    }

    public function test_a_non_school_microsoft_account_is_refused(): void
    {
        User::factory()->client()->create(['email' => 'juan@contoso.com']);

        $this->startLogin(['userPrincipalName' => 'juan@contoso.com']);

        $this->get(route('microsoft.callback'))->assertSessionHasErrors([
            'microsoft' => 'Sign in with your school Microsoft account. Its address must end in .edu.ph, and juan@contoso.com does not.',
        ]);
        $this->assertGuest();
    }

    public function test_the_mail_attribute_is_never_trusted_for_the_address(): void
    {
        // A tenant admin can type any address into `mail`. Only the sign-in
        // name counts, so this one is refused even though `mail` looks right.
        User::factory()->student()->create(['email' => '02000123456@sti.edu.ph']);

        $this->startLogin([
            'userPrincipalName' => 'attacker@evil.onmicrosoft.com',
            'mail' => '02000123456@sti.edu.ph',
        ]);

        $this->get(route('microsoft.callback'))->assertSessionHasErrors('microsoft');
        $this->assertGuest();
    }

    public function test_a_guest_account_in_another_organisation_is_refused(): void
    {
        $this->startLogin(['userPrincipalName' => 'juan_sti.edu.ph#EXT#@contoso.onmicrosoft.com']);

        $this->get(route('microsoft.callback'))->assertSessionHasErrors([
            'microsoft' => 'That is a guest account in another organisation. Sign in with the Microsoft account your school gave you.',
        ]);
    }

    public function test_an_account_without_a_sign_in_name_is_refused(): void
    {
        $this->startLogin(['userPrincipalName' => null]);

        $this->get(route('microsoft.callback'))->assertSessionHasErrors('microsoft');
        $this->assertGuest();
    }

    public function test_a_deactivated_account_can_not_sign_in_with_microsoft(): void
    {
        User::factory()->student()->create([
            'email' => '02000123456@sti.edu.ph',
            'status' => UserStatus::Deactivated,
        ]);

        $this->startLogin();

        $this->get(route('microsoft.callback'))->assertSessionHasErrors([
            'microsoft' => 'This account has been deactivated. Please contact an administrator.',
        ]);
        $this->assertGuest();
    }

    public function test_an_administrator_can_not_sign_in_through_microsoft(): void
    {
        User::factory()->admin()->create(['email' => 'admin@sti.edu.ph']);

        $this->startLogin(['userPrincipalName' => 'admin@sti.edu.ph']);

        $this->get(route('microsoft.callback'))->assertSessionHasErrors([
            'microsoft' => 'Please login using the Admin Portal.',
        ]);
        $this->assertGuest();
    }

    public function test_a_cancelled_sign_in_says_so(): void
    {
        $this->enableMicrosoft();
        $this->get(route('microsoft.redirect'));

        $this->get(route('microsoft.callback', [
            'error' => 'access_denied',
            'error_description' => 'AADSTS65004: User declined to consent to access the app.',
        ]))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['microsoft' => 'Microsoft sign-in was cancelled.']);
    }

    public function test_a_school_that_has_not_approved_the_app_is_explained(): void
    {
        $this->enableMicrosoft();
        $this->get(route('microsoft.redirect', ['intent' => 'register']));

        $this->get(route('microsoft.callback', [
            'error' => 'access_denied',
            'error_description' => 'AADSTS90094: The grant requires admin permission.',
        ]))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors([
                'microsoft' => "Your school has not approved SDPC for Microsoft sign-in yet. Ask your school's IT office to approve it, or sign up with your school email and a password instead.",
            ]);
    }

    public function test_a_personal_account_refused_by_microsoft_is_explained(): void
    {
        $this->enableMicrosoft();
        $this->get(route('microsoft.redirect'));

        $this->get(route('microsoft.callback', [
            'error' => 'invalid_request',
            'error_description' => 'AADSTS50020: User account from identity provider does not exist in tenant.',
        ]))->assertSessionHasErrors([
            'microsoft' => 'That is a personal Microsoft account. Sign in with the Microsoft account your school gave you.',
        ]);
    }

    public function test_an_expired_or_forged_state_is_refused(): void
    {
        $this->enableMicrosoft();
        $this->get(route('microsoft.redirect'));

        $provider = Mockery::mock(MicrosoftProvider::class);
        $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('microsoft')->once()->andReturn($provider);

        $this->get(route('microsoft.callback'))->assertSessionHasErrors([
            'microsoft' => 'That Microsoft sign-in expired or could not be checked. Please try again.',
        ]);
        $this->assertGuest();
    }

    public function test_a_failure_talking_to_microsoft_is_logged_and_explained(): void
    {
        Log::spy();

        $this->enableMicrosoft();
        $this->get(route('microsoft.redirect'));

        $provider = Mockery::mock(MicrosoftProvider::class);
        $provider->shouldReceive('user')->once()->andThrow(new RuntimeException('Token endpoint unreachable.'));
        Socialite::shouldReceive('driver')->with('microsoft')->once()->andReturn($provider);

        $this->get(route('microsoft.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'microsoft' => 'We could not sign you in with Microsoft. Please try again.',
            ]);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Microsoft sign-in failed.'
                && $context['exception'] === RuntimeException::class)
            ->once();
    }

    public function test_the_microsoft_flow_is_rate_limited(): void
    {
        $this->enableMicrosoft();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->get(route('microsoft.redirect'));
        }

        $this->get(route('microsoft.redirect'))->assertTooManyRequests();
    }

    /**
     * Turn the feature on with throwaway credentials.
     */
    private function enableMicrosoft(): void
    {
        config([
            'services.microsoft.client_id' => 'test-client-id',
            'services.microsoft.client_secret' => 'test-client-secret',
            'services.microsoft.redirect' => 'http://localhost/auth/microsoft/callback',
            'services.microsoft.tenant' => 'organizations',
            'services.microsoft.enabled' => true,
        ]);
    }

    /**
     * Press "Continue with Microsoft" on the sign up screen and come back as
     * the given identity.
     *
     * @param  array<string, string|null>  $raw
     */
    private function startRegistration(array $raw = []): void
    {
        $this->enableMicrosoft();
        $this->get(route('microsoft.redirect', ['intent' => 'register']))
            ->assertRedirectContains('login.microsoftonline.com');

        $this->mockMicrosoftReturns($this->microsoftUser($raw));
    }

    /**
     * Press "Continue with Microsoft" on the login screen and come back as the
     * given identity.
     *
     * @param  array<string, string|null>  $raw
     */
    private function startLogin(array $raw = [], bool $personalAccount = false): void
    {
        $this->enableMicrosoft();
        $this->get(route('microsoft.redirect'))
            ->assertRedirectContains('login.microsoftonline.com');

        $this->mockMicrosoftReturns($this->microsoftUser($raw), $personalAccount);
    }

    /**
     * Build the identity Microsoft Graph would describe, mapped the way the
     * driver maps it — the sign-in name becomes the email.
     *
     * @param  array<string, string|null>  $raw
     */
    private function microsoftUser(array $raw = []): MicrosoftUser
    {
        $raw = array_merge([
            'id' => 'ms-object-id-1',
            'displayName' => 'Juan Dela Cruz',
            'givenName' => 'Juan',
            'surname' => 'Dela Cruz',
            'userPrincipalName' => '02000123456@sti.edu.ph',
            'mail' => '02000123456@sti.edu.ph',
        ], $raw);

        return (new MicrosoftUser)->setRaw($raw)->map([
            'id' => $raw['id'],
            'nickname' => null,
            'name' => $raw['displayName'],
            'email' => $raw['userPrincipalName'],
            'avatar' => null,
        ]);
    }

    /**
     * Make the callback resolve to the given identity instead of calling
     * Microsoft.
     */
    private function mockMicrosoftReturns(MicrosoftUser $microsoftUser, bool $personalAccount = false): void
    {
        $provider = Mockery::mock(MicrosoftProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($microsoftUser);
        $provider->shouldReceive('isConsumerTenant')->andReturn($personalAccount);

        Socialite::shouldReceive('driver')->with('microsoft')->once()->andReturn($provider);
    }
}
