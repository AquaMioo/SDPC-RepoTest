<?php

namespace App\Enums;

/**
 * How a code fared when it was checked.
 *
 * Every failure is told apart internally so the screen can say something
 * useful — "that code has expired" is worth hearing, "incorrect" repeated four
 * times is not. None of them reveal whether an account exists: by the time a
 * code is being checked, the address has already had one sent to it.
 */
enum OneTimePasswordResult: string
{
    case Valid = 'valid';
    case Missing = 'missing';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
    case Mismatch = 'mismatch';

    /**
     * Determine if the code was accepted.
     */
    public function isValid(): bool
    {
        return $this === self::Valid;
    }

    /**
     * The message to put under the code field.
     */
    public function message(): string
    {
        return match ($this) {
            self::Valid => '',
            self::Missing => __('That code is no longer valid. Ask for a new one.'),
            self::Expired => __('That code has expired. Ask for a new one.'),
            self::Exhausted => __('Too many incorrect attempts. Ask for a new code.'),
            self::Mismatch => __('That code is incorrect.'),
        };
    }
}
