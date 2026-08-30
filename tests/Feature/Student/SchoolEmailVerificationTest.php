<?php

namespace Tests\Feature\Student;

use App\Enums\OneTimePasswordPurpose;
use App\Enums\VerificationProvider;
use App\Enums\VerificationStatus;
use App\Models\School;
use App\Models\StudentVerification;
use App\Models\User;
use App\Notifications\Auth\EmailOneTimePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Proving a student is a student with a code mailed to their school address.
 *
 * The free stand-in for SheerID, and weaker than it on purpose: this proves
 * somebody can open mail at a domain an administrator put on the list, not
 * that they are enrolled this term. The tests that matter most here are the
 * ones about what it REFUSES — a lookalike domain, somebody else's address, a
 * code borrowed from another flow.
 */
class SchoolEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('verification.school_email.enabled', true);
    }

    public function test_the_routes_are_absent_until_the_feature_is_switched_on(): void
    {
        config()->set('verification.school_email.enabled', false);

        $this->school();

        $this->actingAs($this->student())
            ->post(route('student.school-email.store'), ['email' => 'juan@sti.edu.ph'])
            ->assertNotFound();
    }

    public function test_the_routes_are_absent_while_no_school_has_a_domain(): void
    {
        /*
         * Availability is what switches the gate on for every student at once.
         * Turning the flag on before any domain is configured would lock the
         * whole platform out of applying with no way to get back in.
         */
        School::factory()->create(['domain' => null]);

        $this->actingAs($this->student())
            ->post(route('student.school-email.store'), ['email' => 'juan@sti.edu.ph'])
            ->assertNotFound();
    }

    public function test_a_code_is_mailed_to_a_recognised_school_address(): void
    {
        Notification::fake();
        $this->school();

        $this->actingAs($this->student())
            ->post(route('student.school-email.store'), ['email' => 'juan@sti.edu.ph'])
            ->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(
            EmailOneTimePassword::class,
            fn (EmailOneTimePassword $notification): bool => $notification->purpose === OneTimePasswordPurpose::SchoolEmail,
        );
    }

    public function test_an_address_on_an_unknown_domain_is_refused(): void
    {
        Notification::fake();
        $this->school();

        $this->actingAs($this->student())
            ->from('/')
            ->post(route('student.school-email.store'), ['email' => 'juan@gmail.com'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_a_lookalike_domain_is_refused(): void
    {
        Notification::fake();
        $this->school();

        /*
         * The whole strength of this route is that the domain is one somebody
         * put on the list deliberately. A suffix or substring match would let
         * every one of these through — the first by registering a subdomain,
         * the second by owning notsti.edu.ph.
         */
        foreach ([
            'juan@sti.edu.ph.attacker.com',
            'juan@notsti.edu.ph',
            'juan@mail.sti.edu.ph',
            'juan@sti.edu.ph.co',
        ] as $address) {
            $this->actingAs($this->student())
                ->from('/')
                ->post(route('student.school-email.store'), ['email' => $address])
                ->assertSessionHasErrors('email');
        }

        Notification::assertNothingSent();
    }

    public function test_the_domain_match_ignores_case(): void
    {
        Notification::fake();
        $this->school();

        $this->actingAs($this->student())
            ->post(route('student.school-email.store'), ['email' => 'Juan@STI.Edu.PH'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_correct_code_records_the_verification(): void
    {
        $school = $this->school();
        $student = $this->student();

        $code = $this->sendAndCaptureCode($student, 'juan@sti.edu.ph');

        $this->actingAs($student)
            ->post(route('student.school-email.confirm'), ['code' => $code])
            ->assertSessionHasNoErrors();

        $verification = StudentVerification::query()->where('user_id', $student->id)->sole();

        $this->assertSame(VerificationProvider::SchoolEmail, $verification->provider);
        $this->assertSame(VerificationStatus::Verified, $verification->status);
        $this->assertSame('juan@sti.edu.ph', $verification->payload['email']);
        $this->assertSame($school->id, $verification->payload['school_id']);
    }

    public function test_verifying_opens_the_gate_that_was_closed(): void
    {
        $this->school();
        $student = $this->student();

        /*
         * The point of the whole feature. While the verifier is available and
         * nothing is proved, this student cannot apply, message or sign.
         */
        $this->assertFalse($student->fresh()->isVerifiedForOperating());

        $code = $this->sendAndCaptureCode($student, 'juan@sti.edu.ph');
        $this->actingAs($student)->post(route('student.school-email.confirm'), ['code' => $code]);

        $this->assertTrue($student->fresh()->isVerifiedForOperating());
    }

    public function test_a_wrong_code_proves_nothing(): void
    {
        $this->school();
        $student = $this->student();

        $this->sendAndCaptureCode($student, 'juan@sti.edu.ph');

        $this->actingAs($student)
            ->from('/')
            ->post(route('student.school-email.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertSame(0, StudentVerification::query()->count());
    }

    public function test_a_code_cannot_be_confirmed_without_asking_for_one(): void
    {
        $this->school();

        $this->actingAs($this->student())
            ->from('/')
            ->post(route('student.school-email.confirm'), ['code' => '123456'])
            ->assertSessionHasErrors('code');
    }

    public function test_one_school_address_cannot_verify_two_accounts(): void
    {
        $school = $this->school();
        $first = $this->student();
        $second = $this->student();

        StudentVerification::create([
            'user_id' => $first->id,
            'provider' => VerificationProvider::SchoolEmail,
            'status' => VerificationStatus::Verified,
            'verified_at' => now(),
            'payload' => ['email' => 'juan@sti.edu.ph', 'school_id' => $school->id],
        ]);

        $this->actingAs($second)
            ->from('/')
            ->post(route('student.school-email.store'), ['email' => 'juan@sti.edu.ph'])
            ->assertSessionHasErrors('email');
    }

    /**
     * A school that issues addresses on sti.edu.ph.
     */
    private function school(): School
    {
        return School::factory()->create([
            'name' => 'STI College San Jose Del Monte',
            'domain' => 'sti.edu.ph',
        ]);
    }

    private function student(): User
    {
        return User::factory()->student()->approved()->create();
    }

    /**
     * Ask for a code and read the one that was actually mailed.
     *
     * The table stores a hash, so the plaintext exists nowhere else — the
     * notification carries it, which is why EmailOneTimePassword keeps its
     * properties public.
     */
    private function sendAndCaptureCode(User $student, string $email): string
    {
        $code = null;

        Notification::fake();

        $this->actingAs($student)->post(route('student.school-email.store'), ['email' => $email]);

        Notification::assertSentOnDemand(
            EmailOneTimePassword::class,
            function (EmailOneTimePassword $notification) use (&$code): bool {
                $code = $notification->code;

                return true;
            },
        );

        return (string) $code;
    }
}
