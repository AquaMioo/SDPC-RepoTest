<?php

namespace App\Enums;

enum AgreementStatus: string
{
    /** Drafted the moment a client accepts a student; only the client can edit it. */
    case Draft = 'draft';

    /** Terms are settled and at least one party still has to sign. */
    case AwaitingSignatures = 'awaiting_signatures';

    /** Both parties signed. This is what puts the project into progress. */
    case Active = 'active';

    /** Replaced by a later version after a change request. */
    case Superseded = 'superseded';

    /** Abandoned before it was ever signed by both sides. */
    case Cancelled = 'cancelled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingSignatures => 'Awaiting signatures',
            self::Active => 'Active',
            self::Superseded => 'Superseded',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Determine if the client may still change the terms.
     *
     * A signature is only meaningful against terms that cannot move underneath
     * it, so editing closes the moment anybody signs.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::AwaitingSignatures], true);
    }

    /**
     * Determine if either party may sign an agreement in this status.
     */
    public function acceptsSignatures(): bool
    {
        return in_array($this, [self::Draft, self::AwaitingSignatures], true);
    }

    /**
     * Determine if this status is the end of the agreement's life.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Superseded, self::Cancelled], true);
    }

    /**
     * Get the tag variant the design uses to render this status.
     */
    public function tagVariant(): string
    {
        return match ($this) {
            self::Active => 'accent',
            self::AwaitingSignatures => 'outline',
            self::Draft, self::Superseded, self::Cancelled => 'neutral',
        };
    }
}
