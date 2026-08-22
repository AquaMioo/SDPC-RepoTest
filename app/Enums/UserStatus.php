<?php

namespace App\Enums;

use App\Http\Middleware\EnsureAccountIsNotMonitored;

enum UserStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Monitored = 'monitored';
    case Deactivated = 'deactivated';

    /**
     * Get the status assigned to every newly created account.
     */
    public static function default(): self
    {
        return self::Pending;
    }

    /**
     * Get every status value, e.g. for validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Approved => __('Approved'),
            self::Monitored => __('Monitored'),
            self::Deactivated => __('Deactivated'),
        };
    }

    /**
     * Determine if an account with this status may sign in.
     *
     * Only deactivated accounts are locked out. Pending and monitored accounts
     * keep working so that reviewing an account never interrupts its owner —
     * and a monitored account has an appeal to write, which it cannot do from
     * outside.
     */
    public function canAuthenticate(): bool
    {
        return $this !== self::Deactivated;
    }

    /**
     * Determine if an account with this status is held back from acting.
     *
     * Monitoring is a hold, not a ban: the account keeps reading the platform
     * and keeps talking to the people it is already working with, but it stops
     * posting work, applying, hiring, signing and speaking publicly until an
     * administrator decides. Deactivated accounts never reach the question —
     * they cannot sign in at all.
     *
     * @see EnsureAccountIsNotMonitored
     */
    public function restrictsActions(): bool
    {
        return $this === self::Monitored;
    }
}
