<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Unverified = 'unverified';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Not submitted',
            self::Pending => 'Under review',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Get the tag variant the design uses to render this status.
     */
    public function tagVariant(): string
    {
        return match ($this) {
            self::Verified => 'accent',
            self::Pending => 'outline',
            self::Unverified, self::Rejected => 'neutral',
        };
    }
}
