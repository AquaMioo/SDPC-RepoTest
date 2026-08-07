<?php

namespace App\Enums;

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
     * keep working so that reviewing an account never interrupts its owner.
     */
    public function canAuthenticate(): bool
    {
        return $this !== self::Deactivated;
    }
}
