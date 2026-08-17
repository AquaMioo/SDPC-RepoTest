<?php

namespace App\Services\Verification;

use App\Contracts\StudentVerifier;
use App\Models\StudentVerification;
use App\Models\User;

/**
 * The shipped default: no third party at all.
 *
 * The platform has no SheerID credentials, so this is what every environment
 * gets until somebody sets them. It starts nothing and claims nothing, which
 * is the honest answer — the alternative would be a button that opens a broken
 * redirect, and a badge nobody actually earned.
 */
class NullStudentVerifier implements StudentVerifier
{
    /**
     * Determine if the verifier is configured well enough to be offered.
     */
    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * Open a verification for the given student.
     */
    public function start(User $student): ?StudentVerification
    {
        return null;
    }

    /**
     * Bring a verification up to date with the provider's current answer.
     *
     * There is no provider to ask, so the row is returned untouched rather
     * than being moved to a status nothing decided.
     */
    public function refresh(StudentVerification $verification): StudentVerification
    {
        return $verification;
    }
}
