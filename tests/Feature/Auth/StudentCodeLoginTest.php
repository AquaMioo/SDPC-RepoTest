<?php

namespace Tests\Feature\Auth;

use App\Enums\OneTimePasswordPurpose;
use App\Models\User;
use App\Notifications\Auth\EmailOneTimePassword;
use App\Services\Verification\OneTimePasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Auth\Concerns\CompletesRegistration;
use Tests\TestCase;

/**
 * Students sign in with a code mailed to their school address.
 *
 * They have no password since 2026-09-20, so this is their way back in unless
 * they bound a Google account. Half of these tests are about what the screen
 * must NOT give away: a code only goes to a real student account, and nothing
 * a visitor can see may differ because of that. See .ai/rules/auth.md.
 */
class StudentCodeLoginTest extends TestCase
{
    use CompletesRegistration, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_a_student_signs_in_with_the_code(): void
    {
        $student = User::factory()->student()->create(['email' => 'juan@sti.edu.ph', 'password' => null]);

        $this->post(route('login.code'), ['email' => ' Juan@STI.edu.ph '])
            ->assertRedirect(route('login'));

        Notification::assertSentOnDemand(
            EmailOneTimePassword::class,
            fn (EmailOneTimePassword $notification): bool => $notification->purpose === OneTimePasswordPurpose::Login,
        );

        $this->post(route('login.code.verify'), ['code' => $this->lastCodeSentTo('juan@sti.edu.ph')])
            ->assertRedirect();

        $this->assertAuthenticatedAs($student);
    }

    public function test_the_login_screen_shows_the_code_step_once_asked(): void
    {
        $this->post(route('login.code'), ['email' => 'juan@sti.edu.ph']);

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('loginCode.email', 'juan@sti.edu.ph')
                ->where('loginCode.codeLength', 6)
                ->where('loginCode.secondsUntilResend', 60));
    }

    public function test_only_a_school_address_may_ask_for_a_code(): void
    {
        $this->from(route('login'))
            ->post(route('login.code'), ['email' => 'juan@gmail.com'])
            ->assertSessionHasErrors(['email' => 'Use your school email. It must end in .edu.ph.']);

        Notification::assertNothingSent();
    }

    public function test_no_code_goes_to_an_address_without_a_student_account(): void
    {
        User::factory()->client()->create(['email' => 'owner@shop.edu.ph']);

        $this->post(route('login.code'), ['email' => 'nobody@sti.edu.ph'])->assertRedirect(route('login'));
        $this->post(route('login.code'), ['email' => 'owner@shop.edu.ph'])->assertRedirect(route('login'));

        Notification::assertNothingSent();
    }

    public function test_the_screen_is_the_same_with_or_without_an_account(): void
    {
        User::factory()->student()->create(['email' => 'juan@sti.edu.ph']);

        $screens = [];

        foreach (['juan@sti.edu.ph', 'nobody@sti.edu.ph'] as $email) {
            $this->post(route('login.code'), ['email' => $email]);

            $this->get(route('login'))->assertInertia(function (Assert $page) use (&$screens): void {
                $screens[] = collect($page->toArray()['props']['loginCode'])->except('email')->all();
            });

            $this->delete(route('login.code.cancel'));
        }

        // Including the resend clock, which the appeal screen once leaked.
        $this->assertSame($screens[0], $screens[1]);
    }

    public function test_every_refused_code_reads_the_same(): void
    {
        User::factory()->student()->create(['email' => 'juan@sti.edu.ph']);

        $messages = [];

        foreach (['juan@sti.edu.ph', 'nobody@sti.edu.ph'] as $email) {
            $this->post(route('login.code'), ['email' => $email]);

            $messages[] = $this->from(route('login'))
                ->post(route('login.code.verify'), ['code' => '000000'])
                ->assertSessionHasErrors('code')
                ->baseResponse->getSession()->get('errors')->first('code');

            $this->delete(route('login.code.cancel'));
        }

        $this->assertSame($messages[0], $messages[1]);
        $this->assertGuest();
    }

    public function test_a_wrong_code_does_not_sign_in(): void
    {
        User::factory()->student()->create(['email' => 'juan@sti.edu.ph']);

        $this->post(route('login.code'), ['email' => 'juan@sti.edu.ph']);
        $wrong = $this->lastCodeSentTo('juan@sti.edu.ph') === '000000' ? '111111' : '000000';

        $this->from(route('login'))
            ->post(route('login.code.verify'), ['code' => $wrong])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_code_signs_in_once(): void
    {
        $student = User::factory()->student()->create(['email' => 'juan@sti.edu.ph']);

        $this->post(route('login.code'), ['email' => 'juan@sti.edu.ph']);
        $code = $this->lastCodeSentTo('juan@sti.edu.ph');

        $this->post(route('login.code.verify'), ['code' => $code]);
        $this->assertAuthenticatedAs($student);

        $this->post(route('logout'));
        $this->assertGuest();

        // The step is gone, so the same code has nothing to open.
        $this->post(route('login.code.verify'), ['code' => $code])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_keep_me_logged_in_is_honoured(): void
    {
        $student = User::factory()->student()->create(['email' => 'juan@sti.edu.ph']);

        $this->post(route('login.code'), ['email' => 'juan@sti.edu.ph']);

        $this->post(route('login.code.verify'), [
            'code' => $this->lastCodeSentTo('juan@sti.edu.ph'),
            'remember' => true,
        ]);

        $this->assertAuthenticatedAs($student);
        $this->assertNotNull($student->refresh()->remember_token);
    }

    public function test_a_code_meant_for_signing_up_does_not_sign_in(): void
    {
        User::factory()->student()->create(['email' => 'juan@sti.edu.ph']);

        // A sign up code for the same address, somehow held.
        app(OneTimePasswordService::class)->send('juan@sti.edu.ph', OneTimePasswordPurpose::Registration);
        $registrationCode = $this->lastCodeSentTo('juan@sti.edu.ph');

        $this->post(route('login.code'), ['email' => 'juan@sti.edu.ph']);
        $loginCode = $this->lastCodeSentTo('juan@sti.edu.ph');

        // Two random six-digit codes can coincide; then there is nothing to show.
        if ($registrationCode === $loginCode) {
            $this->markTestSkipped('The two codes happened to match.');
        }

        $this->from(route('login'))
            ->post(route('login.code.verify'), ['code' => $registrationCode])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_resend_is_refused_inside_the_window_by_the_session_clock(): void
    {
        $this->post(route('login.code'), ['email' => 'nobody@sti.edu.ph']);

        // No code went out, yet the clock runs exactly as it would have.
        $this->from(route('login'))
            ->post(route('login.code.resend'))
            ->assertInertiaFlash('toast.type', 'error');

        $this->travel(61)->seconds();

        $this->from(route('login'))
            ->post(route('login.code.resend'))
            ->assertInertiaFlash('toast.type', 'success');
    }

    public function test_starting_over_forgets_the_address(): void
    {
        $this->post(route('login.code'), ['email' => 'juan@sti.edu.ph']);

        $this->delete(route('login.code.cancel'))->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('loginCode', null));
    }

    public function test_a_signed_in_student_can_not_ask_for_a_code(): void
    {
        $student = User::factory()->student()->create(['email' => 'juan@sti.edu.ph']);

        $this->actingAs($student)
            ->post(route('login.code'), ['email' => 'juan@sti.edu.ph'])
            ->assertRedirect();

        Notification::assertNothingSent();
    }
}
