<?php

namespace App\Contracts;

use App\Models\StudentCredential;
use App\Support\Credentials\VerificationResult;

interface VerifiesStudentCredentials
{
    /**
     * Run the credential through whatever verification is available.
     *
     * Swap the binding in AppServiceProvider to route this at a real
     * verification provider (a school registry API, an ID document service)
     * without touching the controller, job or UI.
     */
    public function verify(StudentCredential $credential): VerificationResult;
}
