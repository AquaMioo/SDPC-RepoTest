<?php

namespace Tests\Feature\Settings;

use App\Enums\OneTimePasswordPurpose;
use App\Models\User;
use App\Notifications\Auth\EmailOneTimePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * An account with no password can still be deleted.
 *
 * Deleting used to ask for the current password only, so every student who
 * signed up with a school-email code — and every client made through Google —
 * had no way to leave. A code mailed to the account's own address stands in
 * for the password there is none of.
 */
class PasswordlessAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_a_passwordless_account_deletes_itself_with_a_mailed_code(): void
    {
        $student = $this->passwordlessStudent();

        $this->actingAs($student)
            ->post(route('profile.destroy.code'))
            ->assertSessionHasNoErrors();

        $this->actingAs($student)
            ->delete(route('profile.destroy'), [
                'code' => $this->codeFor($student->email, OneTimePasswordPurpose::DeleteAccount),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($student->fresh());
    }

    public function test_the_address_is_free_to_sign_up_again_afterwards(): void
    {
        $student = $this->passwordlessStudent();

        $this->actingAs($student)->post(route('profile.destroy.code'));
        $this->actingAs($student)->delete(route('profile.destroy'), [
            'code' => $this->codeFor($student->email, OneTimePasswordPurpose::DeleteAccount),
        ]);

        $this->assertFalse(User::where('email', 'juan@sti.edu.ph')->exists());
    }

    public function test_a_wrong_code_deletes_nothing(): void
    {
        $student = $this->passwordlessStudent();

        $this->actingAs($student)->post(route('profile.destroy.code'));
        $right = $this->codeFor($student->email, OneTimePasswordPurpose::DeleteAccount);

        $this->actingAs($student)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['code' => $right === '000000' ? '111111' : '000000'])
            ->assertSessionHasErrors('code');

        $this->assertNotNull($student->fresh());
    }

    public function test_a_code_is_required_when_there_is_no_password(): void
    {
        $student = $this->passwordlessStudent();

        $this->actingAs($student)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'))
            ->assertSessionHasErrors('code');

        $this->assertNotNull($student->fresh());
    }

    public function test_a_sign_in_code_cannot_delete_the_account(): void
    {
        $student = $this->passwordlessStudent();

        $this->post(route('login.code'), ['email' => $student->email]);
        $loginCode = $this->codeFor($student->email, OneTimePasswordPurpose::Login);

        $this->actingAs($student)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['code' => $loginCode])
            ->assertSessionHasErrors('code');

        $this->assertNotNull($student->fresh());
    }

    public function test_an_account_with_a_password_confirms_with_it_and_gets_no_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.destroy.code'))
            ->assertSessionHasErrors('code');

        Notification::assertNothingSent();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['code' => '123456'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($user->fresh());
    }

    public function test_the_settings_page_says_whether_there_is_a_password(): void
    {
        $this->actingAs($this->passwordlessStudent())
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('hasPassword', false));

        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('hasPassword', true));
    }

    public function test_the_code_email_says_what_it_is_for(): void
    {
        $student = $this->passwordlessStudent();

        $this->actingAs($student)->post(route('profile.destroy.code'));

        Notification::assertSentOnDemand(
            EmailOneTimePassword::class,
            fn (EmailOneTimePassword $notification, array $channels, object $notifiable): bool => $notification->purpose === OneTimePasswordPurpose::DeleteAccount
                && str_contains($notification->toMail($notifiable)->subject, 'delete'),
        );
    }

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

        return $code;
    }
}
