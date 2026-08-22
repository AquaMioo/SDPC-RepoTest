<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;

/**
 * A sign up that has been filled in but not yet proved.
 *
 * Registration validates the form, mails a code to the address on it, and puts
 * the whole payload here until that code comes back. No User row exists in the
 * meantime, which is the point: an address nobody can open never takes a place
 * in the unique index, and an abandoned signup leaves nothing behind but a
 * session that expires.
 *
 * The password rides along in the session, which is server side and already
 * carries the authenticated identity for every other request on this platform.
 * It is dropped the moment the account is created.
 *
 * Modelled on PendingGoogleRegistration, which does the same job for the
 * identity Google vouches for.
 */
final class PendingRegistration
{
    private const SESSION_KEY = 'auth.registration.pending';

    /**
     * Hold a validated sign up until its code comes back.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function put(array $payload, string $email): void
    {
        Session::put(self::SESSION_KEY, [
            'payload' => $payload,
            'email' => $email,
        ]);
    }

    /**
     * Read the sign up without consuming it.
     *
     * The code screen may be reloaded, and a wrong code bounces back to it
     * more than once, so this survives until the account is created or the
     * person starts over.
     *
     * @return array{payload: array<string, mixed>, email: string}|null
     */
    public static function get(): ?array
    {
        /** @var array{payload: array<string, mixed>, email: string}|null $pending */
        $pending = Session::get(self::SESSION_KEY);

        return $pending;
    }

    /**
     * Determine if a sign up is waiting on its code.
     */
    public static function exists(): bool
    {
        return self::get() !== null;
    }

    /**
     * Get the address the code was sent to.
     */
    public static function email(): ?string
    {
        return self::get()['email'] ?? null;
    }

    /**
     * Drop the sign up once it has been used, or abandoned.
     */
    public static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
