<?php

namespace App\Enums;

enum AddendumStatus: string
{
    /** Requested by the client; both parties add to Section II and set Section IV. */
    case Draft = 'draft';

    /** One party signed; the terms are closed and the other side still has to sign. */
    case AwaitingSignatures = 'awaiting_signatures';

    /** Both signed: the down payment is due, then the extended work, then the final balance. */
    case Active = 'active';

    /** The final balance cleared: the extension is paid for and its files are unlocked. */
    case Completed = 'completed';

    /** Withdrawn by the client before both parties signed. */
    case Cancelled = 'cancelled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingSignatures => 'Awaiting signatures',
            self::Active => 'Signed',
            self::Completed => 'Fully paid',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Determine if the addendum is still running: being drafted, signed or paid.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::AwaitingSignatures, self::Active], true);
    }

    /**
     * Determine if either party may still sign.
     */
    public function acceptsSignatures(): bool
    {
        return in_array($this, [self::Draft, self::AwaitingSignatures], true);
    }

    /**
     * Get every status that keeps an addendum running, for queries.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Draft, self::AwaitingSignatures, self::Active];
    }

    /**
     * Get the tag variant the design uses to render this status.
     */
    public function tagVariant(): string
    {
        return match ($this) {
            self::Active, self::Completed => 'accent',
            self::AwaitingSignatures => 'outline',
            self::Draft, self::Cancelled => 'neutral',
        };
    }
}
