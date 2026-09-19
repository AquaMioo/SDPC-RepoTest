<?php

namespace Tests\Feature\Auth;

use App\Enums\OneTimePasswordPurpose;
use App\Enums\UserRole;
use App\Enums\VerificationProvider;
use App\Models\School;
use App\Models\StudentVerification;
use App\Models\User;
use App\Notifications\Auth\EmailOneTimePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Auth\Concerns\CompletesRegistration;
use Tests\TestCase;

/**
 * The Student tab: school email, then its code, then the rest with no password.
 *
 * Modelled on the Client tab's "Continue with Google" (2026-09-20): the address
 * is settled first, and once it is the form asks only for a name and the
 * terms, and the account is created without a password or a second code.
 */
class SchoolEmailCodeRegistrationTest extends TestCase
{
    use CompletesRegistration, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_the_student_tab_starts_on_the_school_email(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('schoolEmailCode', null)
                ->where('schoolEmailProfile', null));
    }

    public function test_a_code_goes_to_the_school_email(): void
    {
        $this->post(route('register.school-email'), ['school_email' => '  Juan.123456@SJDELMONTE.sti.edu.ph '])
            ->assertRedirect(route('register'));

        Notification::assertSentOnDemand(
            EmailOneTimePassword::class,
            fn (EmailOneTimePassword $notification, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'juan.123456@sjdelmonte.sti.edu.ph'
                && $notification->purpose === OneTimePasswordPurpose::Registration,
        );

        $this->get(route('register'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('schoolEmailCode.email', 'juan.123456@sjdelmonte.sti.edu.ph')
                ->where('schoolEmailCode.codeLength', 6)
                ->where('schoolEmailProfile', null));

        // Nothing is created by asking for a code.
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_only_an_edu_ph_address_is_sent_a_code(): void
    {
        $this->from(route('register'))
            ->post(route('register.school-email'), ['school_email' => 'juan@gmail.com'])
            ->assertSessionHasErrors(['school_email' => 'Use your school email. It must end in .edu.ph.']);

        Notification::assertNothingSent();
    }

    public function test_an_address_that_already_has_an_account_is_not_sent_a_code(): void
    {
        User::factory()->student()->create(['email' => 'juan@sti.edu.ph']);

        $this->from(route('register'))
            ->post(route('register.school-email'), ['school_email' => 'juan@sti.edu.ph'])
            ->assertSessionHasErrors(['school_email' => 'An account already uses this school email. Please log in instead.']);

        Notification::assertNothingSent();
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $this->post(route('register.school-email'), ['school_email' => 'juan@sti.edu.ph']);

        $wrong = $this->lastCodeSentTo('juan@sti.edu.ph') === '000000' ? '111111' : '000000';

        $this->from(route('register'))
            ->post(route('register.school-email.verify'), ['code' => $wrong])
            ->assertSessionHasErrors('code');

        $this->get(route('register'))
            ->assertInertia(fn (Assert $page) => $page->where('schoolEmailProfile', null));
    }

    public function test_the_right_code_opens_the_form_with_the_address_locked(): void
    {
        $this->post(route('register.school-email'), ['school_email' => 'juan@sti.edu.ph']);

        $this->post(route('register.school-email.verify'), ['code' => $this->lastCodeSentTo('juan@sti.edu.ph')])
            ->assertRedirect(route('register'));

        $this->get(route('register'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('schoolEmailProfile.email', 'juan@sti.edu.ph')
                ->where('schoolEmailCode', null));
    }

    public function test_the_account_is_created_with_no_password_and_no_second_code(): void
    {
        $this->proveSchoolEmail('juan@sti.edu.ph');

        $this->post(route('register.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'role' => UserRole::Student->value,
            // Ignored: the proved address comes from the session.
            'school_email' => 'somebody.else@sti.edu.ph',
            'terms' => '1',
        ])->assertRedirect();

        $user = User::sole();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('juan@sti.edu.ph', $user->email);
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);

        // One code, for the address. The account itself asked for none.
        Notification::assertSentOnDemandTimes(EmailOneTimePassword::class, 1);
    }

    public function test_a_proved_school_email_can_only_become_a_student(): void
    {
        $this->proveSchoolEmail('juan@sti.edu.ph');

        $this->from(route('register'))
            ->post(route('register.store'), [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'role' => UserRole::Client->value,
                'business_name' => 'Juan Trading',
                'terms' => '1',
            ])
            ->assertSessionHasErrors(['role' => 'A school email can only register a student.']);

        $this->assertSame(0, User::count());
    }

    public function test_the_terms_are_still_required(): void
    {
        $this->proveSchoolEmail('juan@sti.edu.ph');

        $this->from(route('register'))
            ->post(route('register.store'), [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'role' => UserRole::Student->value,
            ])
            ->assertSessionHasErrors('terms');

        $this->assertSame(0, User::count());
    }

    public function test_starting_over_drops_the_code(): void
    {
        $this->post(route('register.school-email'), ['school_email' => 'juan@sti.edu.ph']);
        $code = $this->lastCodeSentTo('juan@sti.edu.ph');

        $this->delete(route('register.identity.forget'))->assertRedirect(route('register'));

        $this->get(route('register'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('schoolEmailCode', null)
                ->where('schoolEmailProfile', null));

        // The old code cannot finish a later attempt.
        $this->assertDatabaseMissing('one_time_passwords', ['email' => 'juan@sti.edu.ph']);
        $this->post(route('register.school-email.verify'), ['code' => $code])->assertRedirect(route('register'));
        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('schoolEmailProfile', null));
    }

    public function test_a_listed_school_is_recorded_as_verified(): void
    {
        $school = School::factory()->create(['domain' => 'sjdelmonte.sti.edu.ph']);

        $this->proveSchoolEmail('juan.123456@sjdelmonte.sti.edu.ph');

        $this->post(route('register.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'role' => UserRole::Student->value,
            'terms' => '1',
        ]);

        $verification = StudentVerification::sole();

        $this->assertSame(VerificationProvider::SchoolEmail, $verification->provider);
        $this->assertSame($school->id, $verification->payload['school_id']);
    }

    public function test_a_student_signed_up_this_way_can_remove_a_bound_google_account(): void
    {
        $this->proveSchoolEmail('juan@sti.edu.ph');

        $this->post(route('register.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'role' => UserRole::Student->value,
            'terms' => '1',
        ]);

        $student = User::sole();
        $student->forceFill(['google_id' => 'google-123', 'google_email' => 'juan.personal@gmail.com'])->save();

        /*
         * No password, but the school address can always be sent a sign in
         * code, so Google is not the only way in.
         */
        $this->actingAs($student)
            ->delete(route('student.google.unlink'))
            ->assertInertiaFlash('toast.message', 'Google account removed.');

        $this->assertNull(User::sole()->google_id);
    }

    /**
     * Send a code to a school address and type it back.
     */
    private function proveSchoolEmail(string $email): void
    {
        $this->post(route('register.school-email'), ['school_email' => $email]);
        $this->post(route('register.school-email.verify'), ['code' => $this->lastCodeSentTo($email)]);
    }
}
