<?php

namespace App\Enums;

enum TransactionType: string
{
    /** A payment attached to a milestone in the signed agreement. */
    case Milestone = 'milestone';

    /** Work agreed after signing, outside the original scope. */
    case Extension = 'extension';

    /**
     * Get the display label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Milestone => 'Milestone',
            self::Extension => 'Extension',
        };
    }
}
