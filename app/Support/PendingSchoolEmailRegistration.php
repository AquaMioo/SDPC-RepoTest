<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;

/**
 * A school address a student is proving, before they have an account.
 *
 * The Student tab of the sign up screen asks for the school address first, mails
 * a code to it, and only then shows the rest of the form — the same order as
 * the Client tab's "Continue with Google", where the identity is settled before
 * the details are (2026-09-20). This holds the address across those steps:
 *
 * - awaiting: a code went to it and has not come back yet;
 * - verified: the code came back, so the address stands in for a password
 *   exactly as a Google or Microsoft identity does, and the account is created
 *   the moment the rest of the form is submitted.
 *
 * It lives in the session rather than the form for the same reason as those
 * identities: an address posted from the browser could be anyone's, whereas
 * this one has demonstrably been opened.
 */
final class PendingSchoolEmailRegistration
{
    private const SESSION_KEY = 'auth.school_email.pending';

    /**
     * Remember the address a code has just been sent to.
     */
    public static function awaitCode(string $email): void
    {
        Session::put(self::SESSION_KEY, ['email' => $email, 'verified' => false]);
    }

    /**
     * Record that the code sent to the address came back correct.
     */
    public static function markVerified(): void
    {
        $email = self::awaitingEmail();

        if ($email !== null) {
            Session::put(self::SESSION_KEY, ['email' => $email, 'verified' => true]);
        }
    }

    /**
     * The address a code is waiting on, if one is.
     */
    public static function awaitingEmail(): ?string
    {
        $pending = self::get();

        return $pending !== null && ! $pending['verified'] ? $pending['email'] : null;
    }

    /**
     * The address the student has proved, if they have.
     */
    public static function verifiedEmail(): ?string
    {
        $pending = self::get();

        return $pending !== null && $pending['verified'] ? $pending['email'] : null;
    }

    /**
     * Determine if a proved school address is waiting to become an account.
     */
    public static function exists(): bool
    {
        return self::verifiedEmail() !== null;
    }

    /**
     * Drop the address once it has been used, or abandoned.
     */
    public static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * @return array{email: string, verified: bool}|null
     */
    private static function get(): ?array
    {
        /** @var array{email: string, verified: bool}|null $pending */
        $pending = Session::get(self::SESSION_KEY);

        return $pending;
    }
}
