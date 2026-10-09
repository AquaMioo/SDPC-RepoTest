<?php

namespace App\Services\Payments;

/**
 * Checks the Paymongo-Signature header on a webhook.
 *
 * The header is "t=<timestamp>,te=<test signature>,li=<live signature>"; each
 * signature is the HMAC-SHA256 of "<timestamp>.<raw body>" keyed with the
 * webhook's secret. Test-mode events carry te, live ones li. Anything that
 * does not match is not from PayMongo and is discarded.
 */
class PayMongoSignature
{
    /**
     * Determine if the raw body was signed with the given secret.
     */
    public static function isValid(string $payload, ?string $header, string $secret): bool
    {
        if ($header === null || $header === '' || $secret === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';

        if ($timestamp === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach (['te', 'li'] as $mode) {
            $given = $parts[$mode] ?? '';

            if ($given !== '' && hash_equals($expected, $given)) {
                return true;
            }
        }

        return false;
    }
}
