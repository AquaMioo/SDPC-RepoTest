<?php

namespace App\Support;

/**
 * A Philippine GCash mobile number: written 09XXXXXXXXX, shown 09******297.
 *
 * The number is a payment credential, so no screen ever receives it whole —
 * not the owner's own Settings, not the addendum, not the printed copy. Only
 * the masked form leaves the server (Data Privacy Act clause, Section VII of
 * the addendum).
 */
class GcashNumber
{
    /** What a stored number must look like: 11 digits starting 09. */
    public const PATTERN = '/^09\d{9}$/';

    /**
     * Bring a typed number to 09XXXXXXXXX: spaces and dashes go, and a
     * +63 / 63 prefix becomes the leading 0.
     */
    public static function normalize(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Hide all but the first two and last three digits: 09******297.
     */
    public static function mask(?string $number): ?string
    {
        if ($number === null || $number === '') {
            return null;
        }

        $length = strlen($number);

        if ($length <= 5) {
            return str_repeat('*', $length);
        }

        return substr($number, 0, 2).str_repeat('*', $length - 5).substr($number, -3);
    }
}
