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

        /*
         * The camera and microphone are allowed to this origin and to nobody
         * else, because video meetings run here.
         *
         * This header read camera=(), microphone=() while the meeting module
         * was being built, which denies them to *every* origin including this
         * one. getUserMedia then fails with NotAllowedError before the browser
         * even consults the person, so granting permission changed nothing and
         * it failed identically on every machine. Screen sharing kept working
         * throughout, because getDisplayMedia answers to display-capture
         * rather than to these two — which made it look like a device problem
         * rather than a policy one.
         *
         * `self` and not `*`: an embedded frame still gets nothing, and
         * X-Frame-Options: DENY above means there should never be one.
         */
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), microphone=(self), geolocation=()',
        );

        /*
         * A signed-in page is never kept by the browser.
         *
         * Without this the document sits in the back/forward cache and is
         * replayed without asking the server anything: sign out of the admin
         * portal, land on the login screen, press Forward, and the dashboard
         * was drawn again from that copy. Nothing was authorised — the session
         * had gone — but the numbers, the account list and the review queue
         * were all still legible.
         *
         * no-store is what disables the back/forward cache; the other two are
         * for proxies and for browsers old enough to want Pragma. Inertia's
         * own history state is handled separately, by ClearInertiaHistory.
         *
         * Only for a signed-in response: a guest's login screen and the public
         * pages are the same for everybody and may be cached normally.
         */
        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

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
