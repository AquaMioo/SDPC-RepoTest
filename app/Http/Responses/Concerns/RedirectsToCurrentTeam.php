<?php

namespace App\Http\Responses\Concerns;

use App\Models\Team;
use App\Support\AuthHome;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
