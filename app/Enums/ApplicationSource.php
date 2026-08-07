<?php

namespace App\Enums;

enum ApplicationSource: string
{
    case Applied = 'applied';
    case Invited = 'invited';

    /**
     * Get the display label for the source.
     */
    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Applied',
            self::Invited => 'Invited by client',
        };
    }
}
