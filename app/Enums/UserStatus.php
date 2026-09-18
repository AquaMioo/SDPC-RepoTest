<?php

namespace App\Enums;

use App\Http\Middleware\ConfineDeactivatedAccounts;
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
     * Determine if an account with this status is held to its settings.
     *
     * Every status may sign in. A deactivated account does so only to reach
     * Settings — where its appeal is written, beside the decision it answers
     * — and everything else is closed to it.
     *
     * @see ConfineDeactivatedAccounts
     */
    public function confinesToSettings(): bool
    {
        return $this === self::Deactivated;
    }

    /**
     * Determine if an account with this status is held back from acting.
     *
     * Monitoring is a hold, not a ban: the account keeps reading the platform
     * and keeps talking to the people it is already working with, but it stops
     * posting work, applying, hiring, signing and speaking publicly until an
     * administrator decides. Deactivated accounts never reach the question —
     * ConfineDeactivatedAccounts keeps them inside Settings.
     *
     * @see EnsureAccountIsNotMonitored
     */
    public function restrictsActions(): bool
    {
        return $this === self::Monitored;
    }
}
