<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The response headers a browser needs to defend the page for us.
 *
 * Deliberately no Content-Security-Policy here. A useful one has to name every
 * origin the app actually loads from — Vite's dev server, the Google avatars on
 * profile cards, the font host — and a wrong CSP fails by silently breaking the
 * page rather than by warning. It is worth adding once the production origins
 * are known, and is called out rather than guessed at.
 */
class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Never let the browser second-guess a declared content type. Without
        // this, a stored document sniffed as HTML could execute.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // The app is never meant to be framed, which closes off clickjacking.
        $response->headers->set('X-Frame-Options', 'DENY');

        // Send the origin to other sites but never the full path, so private
        // URLs such as a credential document never leak through Referer.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Nothing here uses the camera, microphone or location.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS only over a real HTTPS connection: sending it over plain HTTP is
        // meaningless, and sending it from localhost would pin the whole of
        // localhost to HTTPS in the developer's browser.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }
}
