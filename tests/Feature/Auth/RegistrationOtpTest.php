<?php

namespace Tests\Feature\Auth;

use App\Enums\OneTimePasswordPurpose;
use App\Enums\UserRole;
use App\Models\OneTimePassword;
use App\Models\User;
use App\Notifications\Auth\EmailOneTimePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Auth\Concerns\CompletesRegistration;
use Tests\TestCase;

/**
 * The emailed code standing between a sign up form and an account.
 *
 * The thing being proved is not "this person typed an email address" but "this
 * person can open it". So nothing is written until the code comes back: no
 * User row, no team, no client profile, and above all no claim on the address
 * in the unique index.
 */
class RegistrationOtpTest extends TestCase
{
    use CompletesRegistration, RefreshDatabase;

    public function test_registering_sends_a_code_and_creates_nothing(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form())
            ->assertRedirect(route('register.verify'));

        $this->assertGuest();
        $this->assertSame(0, User::count());
        $this->assertDatabaseCount('teams', 0);

        Notification::assertSentOnDemand(EmailOneTimePassword::class);
    }

    public function test_the_code_screen_names_the_address_it_went_to(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form());

        $this->get(route('register.verify'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/verify-otp')
                ->where('email', 'ada@example.com')
                ->where('codeLength', 6),
            );
    }

    public function test_the_code_screen_is_pointless_without_a_pending_sign_up(): void
    {
        $this->get(route('register.verify'))->assertRedirect(route('register'));

        $this->post(route('register.verify.store'), ['code' => '123456'])
            ->assertRedirect(route('register'));
    }

    public function test_the_stored_code_is_hashed(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form());

        $code = $this->lastCodeSentTo('ada@example.com');
        $record = OneTimePassword::firstOrFail();

        // A leaked row must not be a working code.
        $this->assertNotSame($code, $record->code_hash);
        $this->assertTrue(Hash::check($code, $record->code_hash));
    }

    public function test_a_wrong_code_creates_nothing(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form());

        $this->from(route('register.verify'))
            ->post(route('register.verify.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_a_code_expires(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form());
        $code = $this->lastCodeSentTo('ada@example.com');

        $this->travel(11)->minutes();

        $this->from(route('register.verify'))
            ->post(route('register.verify.store'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertSame(0, User::count());
    }

    public function test_a_code_runs_out_of_guesses(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form());
        $code = $this->lastCodeSentTo('ada@example.com');

        // Five wrong guesses is the whole budget for this code.
        foreach (range(1, 5) as $ignored) {
            $this->from(route('register.verify'))
                ->post(route('register.verify.store'), ['code' => '000000']);
        }

        // Even the right code is refused now — the budget is per code, so the
        // way back is a new one, not another attempt.
        $this->from(route('register.verify'))
            ->post(route('register.verify.store'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertSame(0, User::count());
    }

    public function test_a_code_only_works_once(): void
    {
        $this->completeRegistration($this->form());

        $this->assertSame(1, User::count());

        $this->post(route('logout'));

        // The row is consumed, so replaying it has nothing to match against.
        $this->from(route('register.verify'))
            ->post(route('register.verify.store'), ['code' => '123456'])
            ->assertRedirect(route('register'));

        $this->assertSame(1, User::count());
    }

    public function test_asking_for_another_code_replaces_the_first(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form());
        $first = $this->lastCodeSentTo('ada@example.com');

        $this->travel(61)->seconds();

        $this->from(route('register.verify'))->post(route('register.verify.resend'));

        // One live code per address: the replaced one stops working.
        $this->assertSame(1, OneTimePassword::count());

        $this->from(route('register.verify'))
            ->post(route('register.verify.store'), ['code' => $first])
            ->assertSessionHasErrors('code');
    }

    public function test_another_code_is_not_sent_within_the_resend_floor(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form());

        $this->from(route('register.verify'))
            ->post(route('register.verify.resend'))
            ->assertSessionHasNoErrors();

        // The first is still valid, so nothing more went out.
        Notification::assertSentOnDemandTimes(EmailOneTimePassword::class, 1);
    }

    public function test_starting_over_drops_the_code(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form());

        $this->delete(route('register.verify.cancel'))
            ->assertRedirect(route('register'));

        $this->assertSame(0, OneTimePassword::count());
        $this->get(route('register.verify'))->assertRedirect(route('register'));
    }

    public function test_changing_the_address_drops_the_code_sent_to_the_old_one(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form());
        $this->post(route('register.store'), $this->form(['email' => 'grace@example.com']));

        // A code pointed at an address nobody is signing up with any more is
        // just a live secret with no owner.
        $this->assertSame(0, OneTimePassword::where('email', 'ada@example.com')->count());
        $this->assertSame(1, OneTimePassword::where('email', 'grace@example.com')->count());
    }

    public function test_the_address_is_matched_regardless_of_case(): void
    {
        $this->completeRegistration($this->form(['email' => 'Ada@Example.com']));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'Ada@Example.com']);
    }

    /**
     * The window between a code going out and coming back is a window in which
     * somebody else can take the address. It is caught rather than crashing on
     * the unique index.
     */
    public function test_an_address_claimed_while_the_code_was_in_the_post_is_refused(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form());
        $code = $this->lastCodeSentTo('ada@example.com');

        User::factory()->create(['email' => 'ada@example.com']);

        $this->from(route('register.verify'))
            ->post(route('register.verify.store'), ['code' => $code])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    /**
     * The admin portal is deliberately untouched by any of this: administrator
     * accounts are created by the developers and never self-served.
     */
    public function test_an_administrator_signs_in_without_a_code(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
        Notification::assertNothingSent();
    }

    /**
     * A sign up that is going to be rejected anyway must not put this
     * application's mailer at the disposal of whoever filled the form in.
     */
    public function test_no_code_is_sent_for_a_form_that_does_not_validate(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form(['terms' => null]))
            ->assertSessionHasErrors('terms');

        Notification::assertNothingSent();
        $this->assertSame(0, OneTimePassword::count());
    }

    public function test_the_code_is_scoped_to_what_it_was_issued_for(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form());

        // Purpose is part of the key, so a registration code is not an appeal
        // code wearing the same digits.
        $this->assertSame(
            OneTimePasswordPurpose::Registration,
            OneTimePassword::firstOrFail()->purpose,
        );
    }

    /**
     * A valid client sign up.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Client->value,
            'business_name' => 'Zenith Solutions Group',
            'terms' => '1',
            ...$overrides,
        ];
    }
}
