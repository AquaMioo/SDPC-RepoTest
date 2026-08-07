<?php

namespace App\Support\Credentials;

use App\Enums\CredentialStatus;

final class VerificationResult
{
    /**
     * @param  array<int, array{check: string, passed: bool, message: string}>  $checks
     */
    private function __construct(
        public readonly CredentialStatus $status,
        public readonly array $checks,
        public readonly ?string $reason = null,
    ) {}

    /**
     * Every automated check passed; a human still makes the final call.
     *
     * @param  array<int, array{check: string, passed: bool, message: string}>  $checks
     */
    public static function passed(array $checks): self
    {
        return new self(CredentialStatus::NeedsReview, $checks);
    }

    /**
     * A check failed outright, so the submission is refused without review.
     *
     * @param  array<int, array{check: string, passed: bool, message: string}>  $checks
     */
    public static function failed(array $checks, string $reason): self
    {
        return new self(CredentialStatus::Rejected, $checks, $reason);
    }
}
