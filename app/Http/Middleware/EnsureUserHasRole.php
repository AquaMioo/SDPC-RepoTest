<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Ensure the authenticated user holds one of the given roles.
     *
     * @param  Closure(Request): Response  $next
     * @param  string  ...$roles  Role values, e.g. "role:admin".
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException(guards: [(string) config('fortify.guard')]);
        }

        $allowed = array_map(
            fn (string $role): UserRole => UserRole::from($role),
            $roles,
        );

        abort_unless($user->hasRole(...$allowed), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
