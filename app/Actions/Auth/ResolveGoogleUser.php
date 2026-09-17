<?php

namespace App\Actions\Auth;

use App\Enums\AuthPortal;
use App\Enums\GoogleAuthIntent;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class ResolveGoogleUser
{
    /**
     * Resolve the local account for a Google identity.
     *
     * Google is a way of signing in, never a way of signing up. An account has
     * to exist already, because registration collects things Google does not
     * know — the role, the school or business name, and agreement to the terms
     * — and none of that can be inferred from a Google profile.
     *
     * @throws ValidationException
     */
    public function handle(SocialiteUser $googleUser, AuthPortal $portal): User
    {
        $user = $this->findExisting($googleUser);

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => [GoogleAuthIntent::Login->noAccountMessage()],
            ]);
        }

        $this->ensureAccountIsActive($user);
        $this->ensureUserMayUsePortal($user, $portal);

        return $this->link($user, $googleUser, (string) $googleUser->getId());
    }

    /**
     * Find the account this Google identity already belongs to, if any.
     *
     * Matching on either the Google id or the address means someone who
     * registered with a password is recognised the first time they use the
     * button, rather than being told to register all over again.
     *
     * The id is asked first and wins. A student signs in with a school
     * address and binds a personal Google account on top, so the account a
     * Google id is bound to must never lose to some other account that
     * happens to share the Google address.
     *
     * @throws ValidationException
     */
    public function findExisting(SocialiteUser $googleUser): ?User
    {
        return User::query()->where('google_id', (string) $googleUser->getId())->first()
            ?? User::query()->where('email', $this->email($googleUser))->first();
    }

    /**
     * Get the verified email address from the Google identity.
     *
     * @throws ValidationException
     */
    public function email(SocialiteUser $googleUser): string
    {
        $email = $googleUser->getEmail();

        if (blank($email)) {
            throw ValidationException::withMessages([
                'email' => [__('Your Google account did not provide an email address.')],
            ]);
        }

        return mb_strtolower($email);
    }

    /**
     * Ensure the account has not been deactivated by an administrator.
     *
     * @throws ValidationException
     */
    private function ensureAccountIsActive(User $user): void
    {
        if ($user->status->canAuthenticate()) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => [__('This account has been deactivated. Please contact an administrator.')],
        ]);
    }

    /**
     * Attach the Google identity to an existing account.
     *
     * An account that already exists keeps its current role, so a student who
     * has since been upgraded to a client is never duplicated or reset.
     *
     * An account matched on its address that already has a different Google
     * account bound is refused rather than switched over: the bound one is
     * the student's way back in after graduation, and only the student may
     * replace it, from settings.
     *
     * @throws ValidationException
     */
    private function link(User $user, SocialiteUser $googleUser, string $googleId): User
    {
        if ($user->google_id !== null && $user->google_id !== $googleId) {
            throw ValidationException::withMessages([
                'email' => [__('This SDPC account is linked to a different Google account. Sign in with that one instead.')],
            ]);
        }

        $user->forceFill([
            'google_id' => $googleId,
            'google_email' => $user->google_email ?? $this->email($googleUser),
            'avatar' => $user->avatar ?? $googleUser->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }

    /**
     * Ensure the existing account may authenticate through this portal.
     *
     * @throws ValidationException
     */
    private function ensureUserMayUsePortal(User $user, AuthPortal $portal): void
    {
        if ($portal->allows($user->role)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => [$portal->rejectionMessage()],
        ]);
    }
}
