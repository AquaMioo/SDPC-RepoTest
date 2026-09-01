<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The heartbeat behind User::isOnline().
 *
 * Every authenticated request stamps the account, which is what turns "signed
 * in somewhere" into "here now" — the alternative was a socket, and a socket
 * tells you who has a tab open, not who is using the site.
 *
 * Written at most once a minute. An UPDATE behind every page view would be a
 * write per request for a figure read to the nearest few minutes, and it
 * would put the users table in the path of every page in the app.
 */
class TouchLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $this->isDue($user)) {
            /*
             * Quietly, and without timestamps: this is not a change anybody
             * made to the account, so it must not bump updated_at or wake
             * model events on every page.
             */
            User::withoutTimestamps(
                fn () => $user->forceFill(['last_seen_at' => now()])->saveQuietly(),
            );
        }

        return $next($request);
    }

    /**
     * Whether the stamp is old enough to be worth a write.
     */
    protected function isDue(User $user): bool
    {
        return $user->last_seen_at === null
            || $user->last_seen_at->lessThan(now()->subMinute());
    }
}
