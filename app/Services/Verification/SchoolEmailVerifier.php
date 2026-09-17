<?php

namespace App\Services\Verification;

use App\Contracts\StudentVerifier;
use App\Enums\OneTimePasswordPurpose;
use App\Enums\VerificationProvider;
use App\Enums\VerificationStatus;
use App\Models\School;
use App\Models\StudentVerification;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Proving a student is a student with an address their school issued.
 *
 * What this checks is that somebody controls an address on a domain the
 * institution hands out — by reading back a mailed code, or by signing in to
 * the school's own Microsoft tenant. That is weaker than enrolment data —
 * alumni keep addresses, staff share the domain — but it is a claim about an
 * address the school controls rather than one the student typed, and it
 * costs nothing.
 *
 * The row is written by SchoolEmailVerificationController when a code comes
 * back correct, or at sign up when the address was proved there, and
 * hasPassedStudentVerification() reads it from there.
 */
class SchoolEmailVerifier implements StudentVerifier
{
    /**
     * Determine if this route can be offered at all.
     *
     * Two conditions, and the second matters as much as the first: with the
     * flag on but no school carrying a domain, nobody could ever verify, and
     * because availability is what switches the gate on, every student on the
     * platform would be locked out of applying at once. So an unconfigured
     * install reports unavailable and changes nothing.
     */
    public function isAvailable(): bool
    {
        return (bool) config('verification.school_email.enabled')
            && School::query()->whereNotNull('domain')->exists();
    }

    /**
     * Record that this student proved control of a school address.
     *
     * updateOrCreate on the user and provider together: verifying a second
     * time — a new address, a lost account — updates the row rather than
     * stacking up a history of identical confirmations that
     * latestStudentVerification() would have to sort through.
     */
    public function confirm(User $student, string $email, School $school): StudentVerification
    {
        return StudentVerification::updateOrCreate(
            [
                'user_id' => $student->id,
                'provider' => VerificationProvider::SchoolEmail,
            ],
            [
                'status' => VerificationStatus::Verified,
                'verified_at' => now(),
                'failure_reason' => null,
                /*
                 * Kept so an administrator reviewing a disputed account can
                 * see which address and which school were actually proved,
                 * rather than only that something once was.
                 */
                'payload' => [
                    'email' => $email,
                    'school_id' => $school->id,
                    'school_name' => $school->name,
                    'domain' => $school->domain,
                ],
            ],
        );
    }

    /**
     * Record a school address that was proved while signing up.
     *
     * Sign up proves the address — a code sent to it came back, or the
     * school's own Microsoft sign-in vouched for it. So a student on a listed
     * school domain is recorded straight away, whether or not this check is
     * switched on yet.
     *
     * The same two refusals as SchoolEmailVerificationController, made
     * quietly: an address on no listed domain (matched exactly, never by
     * suffix) or one already proved by somebody else records nothing, and the
     * student can still verify from settings later.
     */
    public function confirmAtSignUp(User $student, string $email): ?StudentVerification
    {
        $email = mb_strtolower(trim($email));
        $school = School::forEmailDomain(Str::afterLast($email, '@'));

        if ($school === null) {
            return null;
        }

        $claimedElsewhere = StudentVerification::query()
            ->where('user_id', '!=', $student->id)
            ->whereJsonContains('payload->email', $email)
            ->exists();

        return $claimedElsewhere ? null : $this->confirm($student, $email, $school);
    }

    /**
     * The purpose school-email codes are issued under.
     *
     * Its own case rather than Registration's: the purpose is part of the OTP
     * key, so a code mailed to finish signing up cannot be replayed here to
     * claim somebody is enrolled. See .ai/rules/auth.md.
     */
    public function purpose(): OneTimePasswordPurpose
    {
        return OneTimePasswordPurpose::SchoolEmail;
    }
}
