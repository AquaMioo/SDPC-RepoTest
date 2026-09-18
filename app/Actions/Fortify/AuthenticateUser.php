<?php

namespace App\Actions\Fortify;

use App\Enums\AuthPortal;
use App\Models\User;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AuthenticateUser
{
    public function __construct(private readonly UserProvider $provider) {}

    /**
     * Validate the incoming credentials for the portal the request came from.
     *
     * Returning null lets Fortify handle the failure (generic "these
     * credentials do not match" message plus a rate limiter hit), so invalid
     * credentials never reveal whether the account exists or which role it has.
     *
     * @throws ValidationException
     */
    public function __invoke(Request $request): ?User
    {
        $user = $this->retrieveUser($request);

        if ($user === null) {
            return null;
        }

        // A deactivated account signs in too: ConfineDeactivatedAccounts keeps
        // it inside Settings, where its appeal is written.
        $this->ensureUserMayUsePortal($request, $user);

        return $user;
    }

    /**
     * Retrieve the user matching the given credentials, if any.
     */
    private function retrieveUser(Request $request): ?User
    {
        $user = $this->provider->retrieveByCredentials([
            Fortify::username() => (string) $request->input(Fortify::username()),
        ]);

        // Accounts created through Google have no password and therefore can
        // not be authenticated with the password form.
        if (! $user instanceof User || $user->password === null) {
            return null;
        }

        $credentials = ['password' => (string) $request->input('password')];

        if (! $this->provider->validateCredentials($user, $credentials)) {
            return null;
        }

        if (config('hashing.rehash_on_login', true)) {
            $this->provider->rehashPasswordIfRequired($user, $credentials);
        }

        return $user;
    }

    /**
     * Ensure the user's role is allowed to authenticate through this portal.
     *
     * The check runs only after the password has been verified, so the portal
     * hint is never exposed to someone who does not already hold the account's
     * credentials.
     *
     * @throws ValidationException
     */
    private function ensureUserMayUsePortal(Request $request, User $user): void
    {
        $portal = AuthPortal::fromRequest($request);

        if ($portal->allows($user->role)) {
            return;
        }

        throw ValidationException::withMessages([
            Fortify::username() => [$portal->rejectionMessage()],
        ]);
    }
}
