<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\VerificationProvider;
use App\Enums\VerificationStatus;
use App\Models\School;
use App\Models\StudentVerification;
use App\Models\User;
use App\Notifications\Auth\EmailOneTimePassword;
use App\Rules\SchoolEmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Auth\Concerns\CompletesRegistration;
use Tests\TestCase;

/**
 * A student signs up with their school address, and only that.
 *
 * There is no separate email for a student any more: the `.edu.ph` address is
 * the one the code goes to and the one they sign in with.
 */
class SchoolEmailRegistrationTest extends TestCase
{
    use CompletesRegistration, RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function schoolAddresses(): array
    {
        return [
            'student number at a school' => ['02000123456@sti.edu.ph'],
            'a subdomain of a school' => ['juan.dela.cruz@mail.sti.edu.ph'],
            'upper case' => ['Juan@STI.EDU.PH'],
            'a hyphenated school' => ['juan@st-andrew.edu.ph'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherAddresses(): array
    {
        return [
            'a personal address' => ['juan@gmail.com'],
            'a student number alone' => ['02000123456'],
            'no school before edu.ph' => ['juan@edu.ph'],
            'edu.ph fused into the name' => ['juan@stiedu.ph'],
            'a hyphen instead of a dot' => ['juan@sti-edu.ph'],
            'edu.ph in the middle' => ['juan@sti.edu.ph.example.com'],
            'a trailing dot' => ['juan@sti.edu.ph.'],
            'a label starting with a hyphen' => ['juan@-sti.edu.ph'],
            'another country' => ['juan@school.edu.au'],
            'no mailbox' => ['@sti.edu.ph'],
            'two at signs' => ['juan@x@sti.edu.ph'],
        ];
    }

    #[DataProvider('schoolAddresses')]
    public function test_the_rule_accepts_school_addresses(string $email): void
    {
        $this->assertTrue(SchoolEmailAddress::matches($email));
    }

    #[DataProvider('otherAddresses')]
    public function test_the_rule_refuses_everything_else(string $email): void
    {
        $this->assertFalse(SchoolEmailAddress::matches($email));
    }

    #[DataProvider('otherAddresses')]
    public function test_a_student_can_not_sign_up_without_a_school_address(string $email): void
    {
        Notification::fake();

        $this->from(route('register'))
            ->post(route('register.store'), $this->student(['school_email' => $email]))
            ->assertSessionHasErrors('school_email');

        // Refused before anything is mailed anywhere.
        Notification::assertNothingSent();
        $this->assertSame(0, User::count());
    }

    public function test_the_refusal_says_what_is_wrong(): void
    {
        $this->from(route('register'))
            ->post(route('register.store'), $this->student(['school_email' => 'juan@gmail.com']))
            ->assertSessionHasErrors([
                'school_email' => 'Use your school email. It must end in .edu.ph.',
            ]);
    }

    public function test_the_code_goes_to_the_school_address_and_it_becomes_the_account(): void
    {
        $this->completeRegistration($this->student(['school_email' => '  Juan@STI.edu.ph ']));

        $user = User::firstWhere('email', 'juan@sti.edu.ph');

        $this->assertNotNull($user);
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_email_posted_by_a_student_is_ignored(): void
    {
        // The field is gone from the student form. A crafted request that
        // still sends one does not get a personal address onto the account.
        $this->completeRegistration($this->student([
            'email' => 'juan@gmail.com',
            'school_email' => '02000123456@sti.edu.ph',
        ]));

        $this->assertNull(User::firstWhere('email', 'juan@gmail.com'));
        $this->assertNotNull(User::firstWhere('email', '02000123456@sti.edu.ph'));
    }

    public function test_a_school_address_that_already_has_an_account_is_refused(): void
    {
        User::factory()->student()->create(['email' => '02000123456@sti.edu.ph']);

        Notification::fake();

        $this->from(route('register'))
            ->post(route('register.store'), $this->student(['school_email' => '02000123456@STI.edu.ph']))
            ->assertSessionHasErrors([
                'school_email' => 'An account already uses this school email. Please log in instead.',
            ]);

        Notification::assertNothingSent();
        $this->assertSame(1, User::count());
    }

    public function test_a_client_still_signs_up_with_any_email(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@gmail.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Client->value,
            'business_name' => 'Santos Bakery',
            'terms' => '1',
        ])->assertRedirect(route('register.verify'));

        Notification::assertSentOnDemand(EmailOneTimePassword::class);
    }

    /**
     * Signing up proved the address, so a student on a listed school domain
     * is recorded as verified — switching the school-email check on later
     * must not lock them out.
     */
    public function test_a_listed_school_domain_is_recorded_as_verified(): void
    {
        $school = School::factory()->create(['domain' => 'sti.edu.ph']);

        $this->completeRegistration($this->student(['school_email' => '02000123456@sti.edu.ph']));

        $verification = StudentVerification::sole();

        $this->assertSame(VerificationProvider::SchoolEmail, $verification->provider);
        $this->assertSame(VerificationStatus::Verified, $verification->status);
        $this->assertSame('02000123456@sti.edu.ph', $verification->payload['email']);
        $this->assertSame($school->id, $verification->payload['school_id']);

        config(['verification.school_email.enabled' => true]);

        $this->assertTrue(User::sole()->isVerifiedForOperating());
    }

    /**
     * The gate matches domains exactly. A `.edu.ph` address on a school
     * nobody has listed is a valid sign up, and not a verification.
     */
    public function test_an_unlisted_school_domain_is_not_recorded(): void
    {
        School::factory()->create(['domain' => 'sti.edu.ph']);

        $this->completeRegistration($this->student(['school_email' => 'juan@mail.sti.edu.ph']));

        $this->assertSame(0, StudentVerification::count());
    }

    public function test_an_address_proved_by_somebody_else_is_not_recorded_twice(): void
    {
        $school = School::factory()->create(['domain' => 'sti.edu.ph']);
        $other = User::factory()->student()->create();

        StudentVerification::factory()->verified()->create([
            'user_id' => $other->id,
            'provider' => VerificationProvider::SchoolEmail,
            'payload' => ['email' => '02000123456@sti.edu.ph', 'school_id' => $school->id],
        ]);

        $this->completeRegistration($this->student(['school_email' => '02000123456@sti.edu.ph']));

        $this->assertSame(1, StudentVerification::count());
    }

    public function test_the_form_offers_microsoft_only_when_it_is_configured(): void
    {
        config(['services.microsoft.enabled' => false]);

        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/register')
            ->where('canLoginWithMicrosoft', false)
            ->where('microsoftSetupHint', true)
            ->where('microsoftProfile', null),
        );

        config(['services.microsoft.enabled' => true]);

        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
            ->where('canLoginWithMicrosoft', true)
            ->where('microsoftSetupHint', false),
        );
    }

    /**
     * A student sign up, with the fields the form actually sends.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function student(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Student->value,
            'school_email' => '02000123456@sti.edu.ph',
            'terms' => '1',
        ], $overrides);
    }
}
