<?php

namespace App\Jobs;

use App\Contracts\VerifiesStudentCredentials;
use App\Enums\CredentialStatus;
use App\Models\StudentCredential;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class VerifyStudentCredential implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(public readonly StudentCredential $credential) {}

    /**
     * Run the automated checks and record the outcome.
     */
    public function handle(VerifiesStudentCredentials $verifier): void
    {
        $credential = $this->credential->fresh();

        if ($credential === null || $credential->status->isFinal()) {
            return;
        }

        $result = $verifier->verify($credential);

        $credential->forceFill([
            'status' => $result->status,
            'checks' => $result->checks,
            'reason' => $result->reason,
        ])->save();
    }

    /**
     * Leave a rejected submission behind rather than a stuck one.
     */
    public function failed(Throwable $exception): void
    {
        $credential = $this->credential->fresh();

        if ($credential === null || $credential->status->isFinal()) {
            return;
        }

        $credential->forceFill([
            'status' => CredentialStatus::Rejected,
            'reason' => __('The document could not be checked automatically. Please submit it again.'),
        ])->save();
    }
}
