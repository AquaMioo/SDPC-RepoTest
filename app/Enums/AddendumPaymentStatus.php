<?php

namespace App\Enums;

enum AddendumPaymentStatus: string
{
    /** Not paid yet: due now, or waiting for the work it pays for. */
    case Pending = 'pending';

    /** Cleared through the gateway. Final. */
    case Paid = 'paid';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Not paid',
            self::Paid => 'Fully paid',
        };
    }
}
