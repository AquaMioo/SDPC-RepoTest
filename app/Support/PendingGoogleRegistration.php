<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * A Google identity that has proved itself but has no account yet.
 *
 * Pressing "Continue with Google" on the sign up screen does not create an
 * account — registration still collects the role, the school or business name
 * and agreement to the terms. What it does do is carry the name and address
 * Google vouched for across to the form, so none of it has to be retyped.
 *
 * It lives in the session rather than in the form because the identity is the
 * one thing on that page nobody may edit: a google_id or email posted from the
 * browser could be anyone's, whereas this came straight from Google.
 */
final class PendingGoogleRegistration
{
    private const SESSION_KEY = 'auth.google.pending';

    /**
     * Remember the identity while the person fills in the rest of the form.
     */
    public static function put(SocialiteUser $googleUser, string $email): void
    {
        $name = $googleUser->getName() ?? $googleUser->getNickname() ?? $email;

        Session::put(self::SESSION_KEY, [
            'google_id' => (string) $googleUser->getId(),
            'email' => $email,
            'first_name' => Str::before($name, ' ') ?: $name,
            'last_name' => Str::contains($name, ' ') ? Str::afterLast($name, ' ') : '',
            'avatar' => $googleUser->getAvatar(),
        ]);
    }

    /**
     * Read the identity without consuming it.
     *
     * The sign up screen may be reloaded, and validation may bounce the form
     * back more than once, so this survives until the account is created.
     *
     * @return array{google_id: string, email: string, first_name: string, last_name: string, avatar: string|null}|null
     */
    public static function get(): ?array
    {
        /** @var array{google_id: string, email: string, first_name: string, last_name: string, avatar: string|null}|null $pending */
        $pending = Session::get(self::SESSION_KEY);

        return $pending;
    }

    /**
     * Determine if a Google identity is waiting to be turned into an account.
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
