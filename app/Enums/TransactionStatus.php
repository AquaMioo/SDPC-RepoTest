<?php

namespace App\Enums;

enum TransactionStatus: string
{
    /** Recorded against a milestone, nothing has moved yet. */
    case Pending = 'pending';

    /** The client says they have sent it. */
    case Posted = 'posted';

    /** The student confirmed receipt. */
    case Settled = 'settled';

    /** The transfer did not go through. */
    case Failed = 'failed';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Posted => 'Sent',
            self::Settled => 'Received',
            self::Failed => 'Failed',
        };
    }

    /**
     * Determine if this row counts towards money the student has actually been paid.
     */
    public function countsAsEarned(): bool
    {
        return $this === self::Settled;
    }

    /**
     * Get the tag variant the design uses to render this status.
     */
    public function tagVariant(): string
    {
        return match ($this) {
            self::Settled => 'accent',
            self::Posted => 'outline',
            self::Pending, self::Failed => 'neutral',
        };
    }
}
