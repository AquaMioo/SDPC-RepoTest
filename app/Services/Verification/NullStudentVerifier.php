<?php

namespace App\Services\Verification;

use App\Contracts\StudentVerifier;

/**
 * The shipped default: no enrolment check at all.
 *
 * This is what every environment gets until the school-email check is
 * switched on. It claims nothing, which is the honest answer — and because it
 * is never available, it gates nobody.
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
}
