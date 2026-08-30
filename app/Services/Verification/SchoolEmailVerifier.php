<?php

namespace App\Services\Verification;

use App\Contracts\StudentVerifier;
use App\Enums\OneTimePasswordPurpose;
use App\Enums\VerificationProvider;
use App\Enums\VerificationStatus;
use App\Models\School;
use App\Models\StudentVerification;
use App\Models\User;

/**
 * Proving a student is a student with a code mailed to their school address.
 *
 * The free stand-in for SheerID, and honest about the difference. SheerID
 * checks enrolment data licensed from the institution; this checks that
 * somebody can open mail at a domain the institution hands out. That is a
 * weaker claim — alumni keep addresses, staff share the domain — but it is a
 * claim about an address the school controls rather than one the student
 * typed, and it costs nothing.
 *
 * WHY NOT THE GOOGLE HOSTED-DOMAIN CLAIM, which is stronger: it only works for
 * schools on Google Workspace, and most schools here run Microsoft 365. A
 * check that half the intended students cannot use is not a check. The
 * Microsoft equivalent is the Azure AD `tid` claim if this is ever revisited.
 *
 * This implements StudentVerifier, which is shaped around a provider you get
 * redirected to — so start() has nothing useful to do and says so. The row is
 * written by SchoolEmailVerificationController when a code comes back
 * correct, and hasPassedStudentVerification() reads it from there.
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
     * Open a verification for the given student.
     *
     * Nothing to open. There is no third-party page to send anybody to — the
     * flow is a form on this platform that mails a code, which is
     * SchoolEmailVerificationController's job. Returning null is the
     * contract's own way of saying "not this way", and the settings screen
     * already treats null as "no redirect offered".
     */
    public function start(User $student): ?StudentVerification
    {
        return null;
    }

    /**
     * Bring a verification up to date with the provider's current answer.
     *
     * There is no provider to ask. A code was either read back correctly or it
     * was not, and that was decided when it happened — so the stored row is
     * already the whole truth and refreshing it would invent a round trip.
     */
    public function refresh(StudentVerification $verification): StudentVerification
    {
        return $verification;
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
