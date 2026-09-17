<?php

namespace App\Actions\Auth;

use App\Enums\AuthPortal;
use App\Models\User;
use App\Rules\SchoolEmailAddress;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Turn a school Microsoft sign-in into an SDPC identity.
 *
 * The address used is the account's userPrincipalName — the sign-in name —
 * which is what SocialiteProviders maps to getEmail(). Microsoft only lets a
 * tenant give out sign-in names on domains it has proved it owns, so a
 * `@sti.edu.ph` sign-in name really comes from whoever controls sti.edu.ph.
 * The Graph `mail` attribute is deliberately NOT used: a tenant administrator
 * can type any address into it, which is how the "nOAuth" account takeovers
 * worked.
 *
 * Every refusal is logged with a short reason and no address, so a failed
 * sign-in can be diagnosed from the logs without recording who attempted it.
 */
class ResolveMicrosoftUser
{
    /**
     * Get the school address this Microsoft identity signs in with.
     *
     * @throws ValidationException
     */
    public function email(SocialiteUser $microsoftUser, bool $isPersonalAccount): string
    {
        if ($isPersonalAccount) {
            $this->refuse('personal_account', __('That is a personal Microsoft account. Sign in with the Microsoft account your school gave you.'));
        }

        $email = mb_strtolower(trim((string) $microsoftUser->getEmail()));

        if ($email === '') {
            $this->refuse('missing_email', __('Your Microsoft account did not share a sign-in address. Please try again, or sign up with your school email instead.'));
        }

        // Guests invited into another organisation sign in as
        // "name_domain#EXT#@thatorg.onmicrosoft.com" — never a school address.
        if (str_contains($email, '#ext#')) {
            $this->refuse('guest_account', __('That is a guest account in another organisation. Sign in with the Microsoft account your school gave you.'));
        }

        if (! SchoolEmailAddress::matches($email)) {
            $this->refuse('not_school_domain', __('Sign in with your school Microsoft account. Its address must end in .edu.ph, and :email does not.', [
                'email' => $email,
            ]));
        }

        return $email;
    }

    /**
     * Find the account this Microsoft identity already belongs to, if any.
     *
     * The stored Microsoft id wins over the address, so an account that has
     * been linked is always the one found. Matching on the address as well
     * means a student who signed up with a password and their school email is
     * recognised the first time they use the button.
     */
    public function findExisting(SocialiteUser $microsoftUser, string $email): ?User
    {
        return User::query()->where('microsoft_id', (string) $microsoftUser->getId())->first()
            ?? User::query()->where('email', $email)->first();
    }

    /**
     * Refuse to start a sign up for an identity that already has an account.
     *
     * @throws ValidationException
     */
    public function ensureNotRegistered(SocialiteUser $microsoftUser, string $email): void
    {
        $existing = $this->findExisting($microsoftUser, $email);

        if ($existing !== null) {
            $this->refuse('already_registered', __('This Microsoft account is already registered as a :role. Please log in instead.', [
                'role' => $existing->role->label(),
            ]));
        }
    }

    /**
     * Resolve the local account to sign in, linking the identity to it.
     *
     * Microsoft is a way in and, from the sign up screen, a way to prove the
     * school address — but never an account on its own. Somebody with no
     * account is sent to sign up, where the terms are agreed to.
     *
     * @throws ValidationException
     */
    public function handle(SocialiteUser $microsoftUser, string $email): User
    {
        $user = $this->findExisting($microsoftUser, $email);

        if ($user === null) {
            $this->refuse('no_account', __('No SDPC account uses :email yet. Choose Student on the sign up page and continue with Microsoft there.', [
                'email' => $email,
            ]));
        }

        if (! $user->status->canAuthenticate()) {
            $this->refuse('deactivated', __('This account has been deactivated. Please contact an administrator.'));
        }

        if (! AuthPortal::Public->allows($user->role)) {
            $this->refuse('wrong_portal', AuthPortal::Public->rejectionMessage());
        }

        $microsoftId = (string) $microsoftUser->getId();

        /*
         * Matched on the address, but the account already carries another
         * Microsoft identity. Taking the new one over silently would hand the
         * account to whoever holds it, so refuse instead.
         */
        if ($user->microsoft_id !== null && $user->microsoft_id !== $microsoftId) {
            $this->refuse('different_microsoft_account', __('This SDPC account is linked to a different Microsoft account. Sign in with that one, or with your password.'));
        }

        $user->forceFill([
            'microsoft_id' => $microsoftId,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }

    /**
     * Log why a Microsoft identity was turned away, then say so.
     *
     * @throws ValidationException
     */
    private function refuse(string $reason, string $message): never
    {
        Log::info('Microsoft sign-in refused.', ['reason' => $reason]);

        throw ValidationException::withMessages(['microsoft' => [$message]]);
    }
}
