<?php

namespace App\Enums;

enum ContractRequestStatus: string
{
    /** Waiting on the other side's answer. */
    case Pending = 'pending';

    /** The other side agreed: the contract was cancelled or completed. */
    case Accepted = 'accepted';

    /** The other side said no: the contract carries on. */
    case Declined = 'declined';

    /** Taken back by the side that asked. */
    case Withdrawn = 'withdrawn';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for an answer',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
