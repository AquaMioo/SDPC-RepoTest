<?php

namespace App\Enums;

/**
 * Where an appeal against an account decision has got to.
 *
 * An appeal arrives Pending. Granted restores the account; Denied leaves the
 * decision standing and records why. Both are terminal — an account that wants
 * to be heard again files a new appeal, so the queue never silently reopens.
 */
enum AppealStatus: string
{
    case Pending = 'pending';
    case Granted = 'granted';
    case Denied = 'denied';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting review'),
            self::Granted => __('Granted'),
            self::Denied => __('Denied'),
        };
    }

    /**
     * Whether the appeal still needs an administrator.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
