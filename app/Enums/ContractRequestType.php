<?php

namespace App\Enums;

enum ContractRequestType: string
{
    /** Before both have signed: one side turns the MoA or Addendum down. */
    case Denial = 'denial';

    /** During a running collaboration: one side asks to end it early. */
    case Termination = 'termination';

    /**
     * Get the display label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Denial => 'Denial',
            self::Termination => 'Termination request',
        };
    }
}
