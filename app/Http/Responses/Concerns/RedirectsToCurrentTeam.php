<?php

namespace App\Http\Responses\Concerns;

use App\Models\Team;
use App\Support\AuthHome;
use Illuminate\Http\Request;

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
