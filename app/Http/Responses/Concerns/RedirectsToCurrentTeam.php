<?php

namespace App\Http\Responses\Concerns;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserIsClient;
use App\Http\Middleware\EnsureUserIsStudent;
use App\Models\Team;
use App\Models\User;
use App\Support\AuthHome;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

trait RedirectsToCurrentTeam
{
    /**
     * Resolve where to send the user after an authentication step.
     *
     * Delegates to AuthHome so administrators — who have no team and would
     * otherwise be aborted by currentTeam() below — are routed to the admin
     * portal instead. Every Fortify response in this app funnels through here.
     */
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        return AuthHome::for($request->user(), $redirect);
    }

    /**
     * Where to go after authenticating: the page they were turned away from,
     * but only if this account can actually open it.
     *
     * This replaces redirect()->intended(), which trusted the remembered URL
     * unconditionally. Laravel stores that URL for the BROWSER, not for a
     * person, and every team-scoped URL carries its team's slug — so signing
     * in or signing up as anybody other than whoever was last looking at a
     * page sent them to somebody else's team and a 403. Switching between
     * accounts made it constant.
     *
     * It also looked like it fixed itself. Inertia follows the redirect over
     * XHR and shows the 403 in its error overlay without moving the address
     * bar, so a refresh loaded the page the person was really on and worked.
     *
     * The stored URL is pulled either way, so a rejected one cannot resurface
     * on the next sign-in.
     */
    protected function destinationAfterSignIn(Request $request, string $default): string
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $this->canReach($request->user(), $intended)) {
            return $intended;
        }

        return $default;
    }

    /**
     * Whether the user would get past the gates on the route a URL points at.
     *
     * Mirrors the checks that turned this into a 403 rather than guessing from
     * the path: team membership for team-scoped routes, and the role gates the
     * route itself carries. A URL that matches no GET route is refused — there
     * is nowhere sensible to send anybody.
     */
    protected function canReach(mixed $user, string $url): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($url, 'GET'));
        } catch (Throwable) {
            return false;
        }

        foreach (['current_team', 'team'] as $parameter) {
            $slug = $route->parameter($parameter);

            if ($slug === null) {
                continue;
            }

            $team = $slug instanceof Team ? $slug : Team::query()->where('slug', $slug)->first();

            if ($team === null || ! $user->belongsToTeam($team)) {
                return false;
            }
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            $allowed = match (true) {
                $middleware === EnsureUserIsClient::class => $user->hasRole(UserRole::Client) || $user->hasRole(UserRole::Admin),
                $middleware === EnsureUserIsStudent::class => $user->hasRole(UserRole::Student) || $user->hasRole(UserRole::Admin),
                str_starts_with($middleware, 'role:') => $user->hasRole(...array_map(
                    fn (string $role): UserRole => UserRole::from($role),
                    explode(',', substr($middleware, strlen('role:'))),
                )),
                default => true,
            };

            if (! $allowed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Discard every history entry from before this sign-in.
     *
     * Inertia keeps each visited page's props in the browser's history state,
     * encrypted with a key held in session storage, and rebuilds Back and
     * Forward out of it without asking the server. That is why no response
     * header fixed the back button on its own: pressing Back on a dashboard
     * redrew the login screen from history, pressing Forward redrew the
     * dashboard, and Laravel was never consulted in either direction.
     *
     * clearHistory() rotates the key, so every entry written before this
     * moment stops being readable and Inertia has to ask for those pages
     * again. Rotating it at the moment somebody signs in is what lets a later
     * Back reach App\Http\Middleware\EndSessionOnLoginScreen at all.
     *
     * After the session has been regenerated, never before: Fortify
     * regenerates it while authenticating, and the flag this leaves for the
     * next Inertia response to pick up would go with the old session.
     * App\Http\Responses\LogoutResponse carries the same constraint.
     */
    protected function forgetHistoryFromBeforeSignIn(): void
    {
        Inertia::clearHistory();
    }

    /**
     * Get the team the request is acting on.
     *
     * Only safe for users that belong to a team; administrators do not.
     */
    protected function currentTeam(Request $request): Team
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $team = $user->currentTeam ?? $user->personalTeam();

        abort_if(! $team, 403);

        return $team;
    }
}
