<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;

/**
 * A student signing in with a code mailed to their school address.
 *
 * A code is only really sent when the address belongs to a student account, so
 * everything the sign in screen shows about it must come from here and never
 * from the code row — otherwise the screen answers "is this a student?". The
 * appeal screen learned this the hard way; see .ai/rules/auth.md. In
 * particular the resend clock is written whether or not an email went out.
 */
final class PendingCodeLogin
{
    private const EMAIL_KEY = 'auth.login_code.email';

    private const SENT_AT_KEY = 'auth.login_code.sent_at';

    /**
     * Remember the address a code was asked for, and start the resend clock.
     */
    public static function put(string $email): void
    {
        Session::put(self::EMAIL_KEY, $email);
        self::restartClock();
    }

    /**
     * Start the resend clock again after another code was asked for.
     */
    public static function restartClock(): void
    {
        Session::put(self::SENT_AT_KEY, now()->getTimestamp());
    }

    /**
     * The address a code was asked for, if one was.
     */
    public static function email(): ?string
    {
        $email = Session::get(self::EMAIL_KEY);

        return is_string($email) ? $email : null;
    }

    /**
     * Seconds before another code may be asked for, by the session's clock alone.
     */
    public static function secondsUntilResend(): int
    {
        $sentAt = (int) Session::get(self::SENT_AT_KEY, 0);
        $elapsed = now()->getTimestamp() - $sentAt;

        return max(0, (int) config('otp.resend_after') - $elapsed);
    }

    /**
     * What the sign in screen needs to show the code step, or null.
     *
     * @return array{email: string, codeLength: int, expiresAfter: int, secondsUntilResend: int}|null
     */
    public static function forView(): ?array
    {
        $email = self::email();

        return $email === null ? null : [
            'email' => $email,
            'codeLength' => (int) config('otp.length'),
            'expiresAfter' => (int) config('otp.expires_after'),
            'secondsUntilResend' => self::secondsUntilResend(),
        ];
    }

    /**
     * Drop it once the student is in, or starts over.
     */
    public static function forget(): void
    {
        Session::forget([self::EMAIL_KEY, self::SENT_AT_KEY]);
    }
}
