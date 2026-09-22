<?php

namespace Tests\Feature\Auth;

use App\Enums\OneTimePasswordPurpose;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\Auth\EmailOneTimePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Auth\Concerns\CompletesRegistration;
use Tests\TestCase;

/**
 * A student who signed up with a school-email code may set a password.
 *
 * The code keeps working. A password adds the ordinary way in — school email
 * and password on the login form — either chosen on the last sign up step or
 * set later from Settings, where a code to the school address stands in for
 * the current password there is none of.
 */
class StudentPasswordSetupTest extends TestCase
{
    use CompletesRegistration, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_a_student_may_choose_a_password_on_the_last_sign_up_step(): void
    {
        $this->proveSchoolEmail('juan@sti.edu.ph');

        $this->post(route('register.store'), $this->signUp([
            'password' => 'a-Strong-password-2026',
            'password_confirmation' => 'a-Strong-password-2026',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-Strong-password-2026', User::sole()->password));
    }

    public function test_skipping_the_password_still_signs_up_without_one(): void
    {
        $this->proveSchoolEmail('juan@sti.edu.ph');

        $this->post(route('register.store'), $this->signUp())->assertSessionHasNoErrors();

        $this->assertNull(User::sole()->password);
    }

    public function test_a_chosen_password_must_be_confirmed(): void
    {
        $this->proveSchoolEmail('juan@sti.edu.ph');

        $this->from(route('register'))
            ->post(route('register.store'), $this->signUp([
                'password' => 'a-Strong-password-2026',
                'password_confirmation' => 'something-else-2026',
            ]))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, User::query()->count());
    }

    public function test_a_passwordless_student_sets_one_with_a_code_and_can_then_log_in_with_it(): void
    {
        $student = $this->passwordlessStudent();

        $this->actingAs($student)
            ->post(route('password-setup.code'))
            ->assertSessionHasNoErrors();

        $this->actingAs($student)
            ->post(route('password-setup.store'), [
                'code' => $this->codeFor($student->email, OneTimePasswordPurpose::SetPassword),
                'password' => 'a-Strong-password-2026',
                'password_confirmation' => 'a-Strong-password-2026',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-Strong-password-2026', $student->fresh()->password));

        /* The ordinary login form now takes them. */
        auth()->logout();

        $this->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'a-Strong-password-2026',
        ]);

        $this->assertAuthenticatedAs($student->fresh());
    }

    public function test_a_wrong_code_sets_nothing(): void
    {
        $student = $this->passwordlessStudent();

        $this->actingAs($student)->post(route('password-setup.code'));
        $right = $this->codeFor($student->email, OneTimePasswordPurpose::SetPassword);

        $this->actingAs($student)
            ->post(route('password-setup.store'), [
                'code' => $right === '000000' ? '111111' : '000000',
                'password' => 'a-Strong-password-2026',
                'password_confirmation' => 'a-Strong-password-2026',
            ])
            ->assertSessionHasErrors('code');

        $this->assertNull($student->fresh()->password);
    }

    public function test_a_sign_in_code_cannot_be_used_to_set_a_password(): void
    {
        $student = $this->passwordlessStudent();

        $this->post(route('login.code'), ['email' => $student->email]);
        $loginCode = $this->codeFor($student->email, OneTimePasswordPurpose::Login);

        $this->actingAs($student)
            ->post(route('password-setup.store'), [
                'code' => $loginCode,
                'password' => 'a-Strong-password-2026',
                'password_confirmation' => 'a-Strong-password-2026',
            ])
            ->assertSessionHasErrors('code');

        $this->assertNull($student->fresh()->password);
    }

    public function test_an_account_with_a_password_changes_it_under_security_instead(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)
            ->post(route('password-setup.code'))
            ->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->post(route('password-setup.store'), [
                'code' => '123456',
                'password' => 'a-Strong-password-2026',
                'password_confirmation' => 'a-Strong-password-2026',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        Notification::assertNothingSent();
    }

    public function test_the_code_email_says_what_it_is_for(): void
    {
        $mail = (new EmailOneTimePassword('123456', OneTimePasswordPurpose::SetPassword))->toMail(new User);

        $this->assertSame('Your code to set an SDPC password', $mail->subject);
    }

    /**
     * A student who signed up with a school-email code: no password.
     */
    private function passwordlessStudent(): User
    {
        $student = User::factory()->student()->create(['email' => 'juan@sti.edu.ph']);
        $student->forceFill(['password' => null])->save();

        return $student->fresh();
    }

    /**
     * The code mailed to an address for one purpose.
     */
    private function codeFor(string $email, OneTimePasswordPurpose $purpose): string
    {
        $code = null;

        Notification::assertSentOnDemand(
            EmailOneTimePassword::class,
            function (EmailOneTimePassword $notification, array $channels, object $notifiable) use ($email, $purpose, &$code): bool {
                if ($notifiable->routes['mail'] !== $email || $notification->purpose !== $purpose) {
                    return false;
                }

                $code = $notification->code;

                return true;
            },
        );

        return (string) $code;
    }

    /**
     * The last step of a school-email sign up.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function signUp(array $overrides = []): array
    {
        return [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'role' => UserRole::Student->value,
            'terms' => '1',
            ...$overrides,
        ];
    }

    private function proveSchoolEmail(string $email): void
    {
        $this->post(route('register.school-email'), ['school_email' => $email]);
        $this->post(route('register.school-email.verify'), ['code' => $this->lastCodeSentTo($email)]);
    }
}
