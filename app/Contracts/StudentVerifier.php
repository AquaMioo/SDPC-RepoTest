<?php

namespace App\Contracts;

use App\Models\StudentVerification;
use App\Models\User;

/**
 * A third party that can confirm a student is enrolled.
 *
 * Optional throughout. Nothing on the platform gates on the answer this
 * returns — App\Http\Middleware\EnsureAccountIsVerified still asks the
 * administrator-reviewed credential alone, and User::isVerifiedStudent() only
 * decides whether a badge is drawn. A student who never touches this is not a
 * lesser account.
 *
 * The default binding is NullStudentVerifier, which reports itself unavailable
 * and starts nothing. Point config('sheerid.enabled') at a real programme to
 * swap in SheerIdStudentVerifier.
 */
interface StudentVerifier
{
    /**
     * Determine if the verifier is configured well enough to be offered.
     */
    public function isAvailable(): bool;

    /**
     * Open a verification for the given student.
     *
     * Returns the row to redirect them at, or null when the provider is
     * unavailable or refused to start one. A null here is not an error the
     * student needs to see: they simply carry on without the badge.
     */
    public function start(User $student): ?StudentVerification;

    /**
     * Bring a verification up to date with the provider's current answer.
     */
    public function refresh(StudentVerification $verification): StudentVerification;
}
