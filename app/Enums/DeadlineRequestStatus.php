<?php

namespace App\Enums;

/**
 * Where one ask to move a deadline stands.
 *
 * The student side asks (Pending) and may take it back (Withdrawn); the client
 * decides (Approved moves the date, Declined leaves it). Only Pending can
 * change, and a deadline has at most one Pending ask at a time.
 */
enum DeadlineRequestStatus: string
{
    /** Waiting on the client. */
    case Pending = 'pending';

    /** The client agreed; the deadline moved to the proposed date. */
    case Approved = 'approved';

    /** The client said no; the deadline stayed where it was. */
    case Declined = 'declined';

    /** The student took it back before the client decided. */
    case Withdrawn = 'withdrawn';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for the client',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
