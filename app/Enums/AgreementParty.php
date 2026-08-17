<?php

namespace App\Enums;

enum AgreementParty: string
{
    case Client = 'client';
    case Student = 'student';

    /**
     * Get the display label for the party.
     */
    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client',
            self::Student => 'Student',
        };
    }

    /**
     * Get the party a user signs as, given the role they hold.
     *
     * Administrators are deliberately absent: an agreement is between the two
     * sides doing the work, and nobody signs on their behalf.
     */
    public static function forRole(UserRole $role): ?self
    {
        return match ($role) {
            UserRole::Client => self::Client,
            UserRole::Student => self::Student,
            UserRole::Admin => null,
        };
    }

    /**
     * Get the other side of the agreement.
     */
    public function counterparty(): self
    {
        return $this === self::Client ? self::Student : self::Client;
    }
}
