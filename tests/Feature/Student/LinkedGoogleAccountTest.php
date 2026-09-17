<?php

namespace Tests\Feature\Student;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * A personal Google account bound to a student, for after graduation.
 *
 * The student signs in with a school address that closes when they leave.
 * Whatever is bound here has to keep working once it does — and must never
 * become a way into somebody else's account.
 */
class LinkedGoogleAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_linking_sends_the_student_to_google_with_its_own_callback(): void
    {
        $this->enableGoogle();

        $response = $this->actingAs($this->student())->get(route('student.google.link'));

        $response->assertRedirectContains('accounts.google.com');
        $response->assertRedirectContains('prompt=select_account');
        $response->assertRedirectContains(urlencode(route('student.google.callback')));
    }

    public function test_the_routes_are_closed_while_google_is_not_configured(): void
    {
        config(['services.google.enabled' => false]);

        $student = $this->student();

        $this->actingAs($student)->get(route('student.google.link'))->assertNotFound();
        $this->actingAs($student)->get(route('student.google.callback'))->assertNotFound();
    }

    public function test_only_signed_in_students_can_link(): void
    {
        $this->enableGoogle();

        $this->get(route('student.google.link'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->client()->create())
            ->get(route('student.google.link'))
            ->assertForbidden();
    }

    public function test_a_personal_google_account_is_linked(): void
    {
        $student = $this->student();

        $this->actingAs($student);
        $this->mockGoogleReturns($this->googleUser());

        $this->get(route('student.google.callback'))
            ->assertRedirect(route('profile.edit'))
            ->assertInertiaFlash('toast.type', 'success');

        $student->refresh();

        $this->assertSame('google-123', $student->google_id);
        $this->assertSame('juan.personal@gmail.com', $student->google_email);
        // The school address is still the one they sign in with.
        $this->assertSame('02000123456@sti.edu.ph', $student->email);
    }

    public function test_the_linked_account_signs_the_student_in_after_graduation(): void
    {
        $student = $this->student([
            'google_id' => 'google-123',
            'google_email' => 'juan.personal@gmail.com',
        ]);

        // A guest pressing "Continue with Google" on the login screen.
        $this->enableGoogle();
        $this->get(route('google.redirect'))->assertRedirectContains('accounts.google.com');
        $this->mockGoogleReturns($this->googleUser(), expectsRedirectUrl: false);

        $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_the_bound_account_wins_over_another_account_on_the_same_address(): void
    {
        $student = $this->student([
            'google_id' => 'google-123',
            'google_email' => 'juan.personal@gmail.com',
        ]);
        User::factory()->client()->create(['email' => 'juan.personal@gmail.com']);

        $this->enableGoogle();
        $this->get(route('google.redirect'));
        $this->mockGoogleReturns($this->googleUser(), expectsRedirectUrl: false);

        $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_a_different_google_account_can_not_take_over_a_bound_account(): void
    {
        // The account's own address is a Google address, but a different
        // Google account is already bound to it.
        $client = User::factory()->client()->create([
            'email' => 'juan.personal@gmail.com',
            'google_id' => 'google-OTHER',
            'google_email' => 'someone@gmail.com',
        ]);

        $this->enableGoogle();
        $this->get(route('google.redirect'));
        $this->mockGoogleReturns($this->googleUser(), expectsRedirectUrl: false);

        $this->get(route('google.callback'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame('google-OTHER', $client->refresh()->google_id);
    }

    public function test_a_school_google_account_is_refused(): void
    {
        $student = $this->student();

        $this->actingAs($student);
        $this->mockGoogleReturns($this->googleUser(['email' => '02000123456@sti.edu.ph']));

        $this->get(route('student.google.callback'))
            ->assertRedirect(route('profile.edit'))
            ->assertInertiaFlash('toast.type', 'error')
            ->assertInertiaFlash(
                'toast.message',
                'Link a personal Google account. 02000123456@sti.edu.ph is a school account and will close when you graduate.',
            );

        $this->assertNull($student->refresh()->google_id);
    }

    public function test_a_google_account_bound_to_somebody_else_is_refused(): void
    {
        $this->student(['email' => 'other@sti.edu.ph', 'google_id' => 'google-123']);
        $student = $this->student();

        $this->actingAs($student);
        $this->mockGoogleReturns($this->googleUser());

        $this->get(route('student.google.callback'))
            ->assertInertiaFlash('toast.message', 'That Google account is already linked to another SDPC account.');

        $this->assertNull($student->refresh()->google_id);
    }

    public function test_a_google_address_that_is_another_accounts_address_is_refused(): void
    {
        User::factory()->client()->create(['email' => 'juan.personal@gmail.com']);
        $student = $this->student();

        $this->actingAs($student);
        $this->mockGoogleReturns($this->googleUser());

        $this->get(route('student.google.callback'))
            ->assertInertiaFlash('toast.message', 'That Google address belongs to another SDPC account.');

        $this->assertNull($student->refresh()->google_id);
    }

    public function test_linking_again_replaces_the_earlier_account(): void
    {
        $student = $this->student([
            'google_id' => 'google-OLD',
            'google_email' => 'old@gmail.com',
        ]);

        $this->actingAs($student);
        $this->mockGoogleReturns($this->googleUser());

        $this->get(route('student.google.callback'));

        $student->refresh();

        $this->assertSame('google-123', $student->google_id);
        $this->assertSame('juan.personal@gmail.com', $student->google_email);
    }

    public function test_a_cancelled_link_changes_nothing(): void
    {
        $this->enableGoogle();
        $student = $this->student();

        $this->actingAs($student)
            ->get(route('student.google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('profile.edit'))
            ->assertInertiaFlash('toast.message', 'Linking Google was cancelled.');

        $this->assertNull($student->refresh()->google_id);
    }

    public function test_an_expired_link_changes_nothing(): void
    {
        $this->enableGoogle();
        $student = $this->student();

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->actingAs($student)
            ->get(route('student.google.callback'))
            ->assertInertiaFlash('toast.message', 'That Google sign-in expired. Please try again.');

        $this->assertNull($student->refresh()->google_id);
    }

    public function test_a_student_with_another_way_in_can_remove_google(): void
    {
        $student = $this->student([
            'google_id' => 'google-123',
            'google_email' => 'juan.personal@gmail.com',
        ]);

        $this->actingAs($student)
            ->delete(route('student.google.unlink'))
            ->assertRedirect(route('profile.edit'))
            ->assertInertiaFlash('toast.message', 'Google account removed.');

        $student->refresh();

        $this->assertNull($student->google_id);
        $this->assertNull($student->google_email);
    }

    public function test_google_can_not_be_removed_when_it_is_the_only_way_in(): void
    {
        $student = $this->student([
            'email' => 'juan.personal@gmail.com',
            'password' => null,
            'google_id' => 'google-123',
            'google_email' => 'juan.personal@gmail.com',
        ]);

        $this->actingAs($student)
            ->delete(route('student.google.unlink'))
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertSame('google-123', $student->refresh()->google_id);
    }

    public function test_settings_describes_a_students_sign_in_methods(): void
    {
        $this->enableGoogle();

        $student = $this->student([
            'microsoft_id' => 'ms-1',
            'google_id' => 'google-123',
            'google_email' => 'juan.personal@gmail.com',
        ]);

        $this->actingAs($student)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('signInMethods.hasPassword', true)
                ->where('signInMethods.microsoftLinked', true)
                ->where('signInMethods.googleAvailable', true)
                ->where('signInMethods.googleLinked', true)
                ->where('signInMethods.googleEmail', 'juan.personal@gmail.com')
                ->where('signInMethods.canUnlinkGoogle', true)
                ->missing('studentVerification'),
            );
    }

    public function test_settings_shows_no_sign_in_methods_to_a_client(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('signInMethods', null));
    }

    public function test_the_bound_account_is_never_serialised_with_the_user(): void
    {
        $student = $this->student([
            'microsoft_id' => 'ms-1',
            'google_id' => 'google-123',
            'google_email' => 'juan.personal@gmail.com',
        ]);

        $this->assertArrayNotHasKey('google_email', $student->toArray());
        $this->assertArrayNotHasKey('microsoft_id', $student->toArray());
    }

    /**
     * A student who signs in with their school address.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function student(array $attributes = []): User
    {
        return User::factory()->student()->create(array_merge([
            'email' => '02000123456@sti.edu.ph',
        ], $attributes));
    }

    /**
     * Turn Google on with throwaway credentials.
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
     * Build the identity Google would hand back.
     *
     * @param  array<string, string|null>  $attributes
     */
    private function googleUser(array $attributes = []): SocialiteUser
    {
        return (new SocialiteUser)->map(array_merge([
            'id' => 'google-123',
            'name' => 'Juan Dela Cruz',
            'nickname' => null,
            'email' => 'juan.personal@gmail.com',
            'avatar' => 'https://example.com/juan.jpg',
        ], $attributes));
    }

    /**
     * Make the next Google callback resolve to the given identity.
     *
     * The link flow points the driver at its own callback first; the login
     * flow does not.
     */
    private function mockGoogleReturns(SocialiteUser $googleUser, bool $expectsRedirectUrl = true): void
    {
        $this->enableGoogle();

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        if ($expectsRedirectUrl) {
            $provider->shouldReceive('redirectUrl')
                ->once()
                ->with(route('student.google.callback'))
                ->andReturnSelf();
        }

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }
}
