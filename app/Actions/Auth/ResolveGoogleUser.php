<?php

namespace App\Actions\Auth;

use App\Enums\AuthPortal;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class ResolveGoogleUser
{
    /**
     * Resolve the local account for a Google identity.
     *
     * Students and clients may create an account this way; the very first
     * Google sign in creates the account with the default role. Administrator
     * accounts are never created here — an admin may only sign in when the
     * account already exists and already holds the admin role.
     *
     * @param  UserRole|null  $intendedRole  The role chosen on the sign up form,
     *                                       used only when creating an account.
     *
     * @throws ValidationException
     */
    public function handle(SocialiteUser $googleUser, AuthPortal $portal, ?UserRole $intendedRole = null): User
    {
        $email = $this->email($googleUser);
        $googleId = (string) $googleUser->getId();

        $user = User::query()
            ->where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        if ($user === null) {
            return $this->create($googleUser, $email, $googleId, $portal, $intendedRole);
        }

        $this->ensureAccountIsActive($user);
        $this->ensureUserMayUsePortal($user, $portal);

        return $this->link($user, $googleUser, $googleId);
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
     * Create a brand new account for the Google identity.
     *
     * @throws ValidationException
     */
    private function create(
        SocialiteUser $googleUser,
        string $email,
        string $googleId,
        AuthPortal $portal,
        ?UserRole $intendedRole,
    ): User {
        // Registration is only ever possible through the public portal, so an
        // unknown Google identity can never become an administrator.
        if ($portal !== AuthPortal::Public) {
            throw ValidationException::withMessages([
                'email' => [__('No administrator account exists for this Google account.')],
            ]);
        }

        // The role is re-checked here rather than trusted from the session, so
        // a tampered value still cannot produce anything but student or client.
        $role = $intendedRole?->isSelfRegisterable() ? $intendedRole : UserRole::default();

        $name = $googleUser->getName() ?? $googleUser->getNickname() ?? $email;

        $user = new User;

        $user->forceFill([
            'name' => $name,
            'first_name' => Str::before($name, ' ') ?: $name,
            'last_name' => Str::contains($name, ' ') ? Str::afterLast($name, ' ') : null,
            'email' => $email,
            'role' => $role,
            'status' => UserStatus::default(),
            'google_id' => $googleId,
            'avatar' => $googleUser->getAvatar(),
            'password' => null,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * Attach the Google identity to an existing account.
     *
     * An account that already exists keeps its current role, so a student who
     * has since been upgraded to a client is never duplicated or reset.
     */
    private function link(User $user, SocialiteUser $googleUser, string $googleId): User
    {
        $user->forceFill([
            'google_id' => $googleId,
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

    /**
     * Get the verified email address from the Google identity.
     *
     * @throws ValidationException
     */
    private function email(SocialiteUser $googleUser): string
    {
        $email = $googleUser->getEmail();

        if (blank($email)) {
            throw ValidationException::withMessages([
                'email' => [__('Your Google account did not provide an email address.')],
            ]);
        }

        return mb_strtolower($email);
    }
}
