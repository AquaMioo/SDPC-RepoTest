<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\URL;

final class AuthHome
{
    /**
     * Get the path the given user should land on once authenticated.
     *
     * This is the single place that decides where each role "lives" after
     * logging in, and is reused by every Fortify response, the guest middleware
     * redirect and the Google callback.
     *
     * Order matters. Administrators are resolved first because they live
     * outside the team-scoped half of the platform and have no team at all —
     * running the team lookup on them would abort the request.
     *
     * @param  string|null  $redirect  Path within the team, e.g. "/dashboard".
     */
    public static function for(Authenticatable|User|null $user, ?string $redirect = null): string
    {
        $redirect ??= (string) config('fortify.home');

        if (! $user instanceof User) {
            return '/';
        }

        if ($user->isAdmin()) {
            return route('admin.dashboard', absolute: false);
        }

        $team = $user->currentTeam ?? $user->personalTeam();

        // A client or student without a team is a broken account rather than an
        // unauthorised one, so send them somewhere harmless instead of a 403.
        if ($team === null) {
            return $redirect;
        }

        // Team-scoped routes take their slug from the URL defaults, so it has
        // to be registered before anything calls route() on this request.
        URL::defaults(['current_team' => $team->slug]);

        return "/{$team->slug}{$redirect}";
    }
}
