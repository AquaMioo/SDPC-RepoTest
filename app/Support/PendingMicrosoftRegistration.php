<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * A school Microsoft identity that has proved itself but has no account yet.
 *
 * The student counterpart of PendingGoogleRegistration. Pressing "Continue
 * with Microsoft" on the sign up screen does not create an account — the terms
 * still have to be agreed to — but the school address Microsoft vouched for is
 * carried across, so it is neither retyped nor sent a code.
 *
 * It lives in the session rather than in the form for the same reason as the
 * Google identity: an address posted from the browser could be anyone's,
 * whereas this came straight from the school's own sign-in.
 */
final class PendingMicrosoftRegistration
{
    private const SESSION_KEY = 'auth.microsoft.pending';

    /**
     * Remember the identity while the student finishes the form.
     *
     * Microsoft sends the given name and surname separately, which beats
     * splitting the display name — "Dela Cruz" stays one surname.
     */
    public static function put(SocialiteUser $microsoftUser, string $email): void
    {
        $raw = $microsoftUser instanceof AbstractUser ? $microsoftUser->getRaw() : [];
        $name = trim((string) ($microsoftUser->getName() ?? ''));

        $firstName = is_string($raw['givenName'] ?? null) ? $raw['givenName'] : Str::before($name, ' ');
        $lastName = is_string($raw['surname'] ?? null)
            ? $raw['surname']
            : (Str::contains($name, ' ') ? Str::afterLast($name, ' ') : '');

        Session::put(self::SESSION_KEY, [
            'microsoft_id' => (string) $microsoftUser->getId(),
            'email' => $email,
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
        ]);
    }

    /**
     * Read the identity without consuming it.
     *
     * @return array{microsoft_id: string, email: string, first_name: string, last_name: string}|null
     */
    public static function get(): ?array
    {
        /** @var array{microsoft_id: string, email: string, first_name: string, last_name: string}|null $pending */
        $pending = Session::get(self::SESSION_KEY);

        return $pending;
    }

    /**
     * Determine if a Microsoft identity is waiting to be turned into an account.
     */
    public static function exists(): bool
    {
        return self::get() !== null;
    }

    /**
     * Drop the identity once it has been used, or abandoned.
     */
    public static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
