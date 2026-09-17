<?php

namespace App\Contracts;

/**
 * A way of confirming a student is enrolled.
 *
 * Availability is the whole contract, because availability is what switches
 * the gate: User::hasPassedStudentVerification() lets every student through
 * while the bound verifier reports itself unavailable, and waits on a confirmed
 * StudentVerification row once it does not.
 *
 * The default binding is NullStudentVerifier, which is never available.
 * Switching config('verification.school_email.enabled') on, with at least one
 * school carrying a domain, swaps in SchoolEmailVerifier.
 */
interface StudentVerifier
{
    /**
     * Determine if the verifier is configured well enough to be offered.
     */
    public function isAvailable(): bool;
}
