<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the Student Module's routes closed to client accounts.
 *
 * The mirror of EnsureUserIsClient, down to letting administrators through so
 * support staff can see what a student sees without a second login.
 */
class EnsureUserIsStudent
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
            $user->hasRole(UserRole::Student) || $user->hasRole(UserRole::Admin),
            403,
        );

        return $next($request);
    }
}
