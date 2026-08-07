<?php

namespace App\Enums;

enum CredentialStatus: string
{
    /** Uploaded, waiting for the automated checks to run. */
    case Pending = 'pending';

    /** Automated checks passed; an administrator makes the final call. */
    case NeedsReview = 'needs_review';

    /** An administrator confirmed the credential. */
    case Verified = 'verified';

    /** Automated checks or an administrator rejected the credential. */
    case Rejected = 'rejected';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Checking…'),
            self::NeedsReview => __('Awaiting review'),
            self::Verified => __('Verified'),
            self::Rejected => __('Rejected'),
        };
    }

    /**
     * Determine if the student may submit another document.
     */
    public function allowsResubmission(): bool
    {
        return $this === self::Rejected;
    }

    /**
     * Determine if this status closes the submission.
     */
    public function isFinal(): bool
    {
        return $this === self::Verified || $this === self::Rejected;
    }
}
