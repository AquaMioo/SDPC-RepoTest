<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the Client Module's routes closed to student accounts.
 *
 * Administrators pass through so support staff can inspect a client's
 * workspace without a second login.
 */
class EnsureUserIsClient
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);
        abort_unless(
            $user->hasRole(UserRole::Client) || $user->hasRole(UserRole::Admin),
            403,
        );

        return $next($request);
    }
}
