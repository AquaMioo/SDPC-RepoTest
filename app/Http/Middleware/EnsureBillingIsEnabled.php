<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the money screens out of reach while billing is switched off.
 *
 * A 404 rather than a 403: while the feature is off it does not exist as far
 * as anybody outside the codebase is concerned, and a "forbidden" would
 * advertise a screen nobody can reach yet.
 */
class EnsureBillingIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('billing.enabled'), 404);

        return $next($request);
    }
}
