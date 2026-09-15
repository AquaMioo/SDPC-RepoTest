<?php

namespace App\Enums;

/**
 * Where one checklist item stands.
 *
 * Two people move it, in opposite directions and never each other's half: the
 * student checks it off (Open → Submitted), the client verifies it (Submitted →
 * Verified) or sends it back (Submitted → Open, with a note). Only Verified
 * counts towards progress.
 */
enum TaskStatus: string
{
    /** Not handed over yet, or sent back by the client. */
    case Open = 'open';

    /** The student checked it off; waiting on the client's review. */
    case Submitted = 'submitted';

    /** The client confirmed it is done. */
    case Verified = 'verified';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'To do',
            self::Submitted => 'Pending client review',
            self::Verified => 'Verified',
        };
    }
}
