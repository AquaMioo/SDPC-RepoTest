<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reaching a login screen while signed in ends the session.
 *
 * Press Back on the admin dashboard and the browser asks for the login screen.
 * Leaving the session alive at that point means Forward walks straight back
 * into the dashboard, and on a shared machine that is the whole problem: the
 * person who used the computer before you is still signed in, and two arrow
 * keys are enough to reach their portal.
 *
 * So arriving at a login screen is treated as the end of the visit. The form
 * renders for a signed-out visitor, and Forward then meets the auth middleware
 * and is sent back here to type an email and a password again.
 *
 * The trade-off is deliberate and worth knowing before changing it: this fires
 * on ANY arrival at a login screen while signed in, not only on Back — an old
 * bookmark or a "Log in" link in an email will end the session too. The server
 * cannot tell a history navigation from a typed URL, and the safe reading of
 * "take me to the login page" is that the person means to log in.
 *
 * It only works because AddSecurityHeaders marks these screens no-store. A
 * login page replayed out of the back/forward cache never reaches the app, and
 * this middleware never runs at all.
 *
 * The order inside handle() matters and matches App\Http\Responses\
 * LogoutResponse: clearHistory() leaves a flag in the session for the next
 * Inertia response to act on, so invalidating the session after it would throw
 * that flag away and Inertia would keep serving the dashboard from history.
 */
class EndSessionOnLoginScreen
{
    /**
     * The login screens, one per portal.
     *
     * Named exactly rather than matched by pattern. The two-factor challenge
     * is also a *.login route and must stay reachable by somebody who is
     * halfway through signing in, so a pattern would break it. A new portal's
     * login screen has to be added to this list.
     *
     * @var list<string>
     */
    private const SCREENS = ['login', 'admin.login'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldEndSession($request)) {
            Auth::guard((string) config('fortify.guard'))->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Inertia::clearHistory();
        }

        return $next($request);
    }

    /**
     * Whether this request is a signed-in visitor arriving at a login screen.
     *
     * GET only: the sign-in form posts to the same names, and a POST that
     * ended the session before Fortify could read the credentials would make
     * logging in impossible.
     */
    private function shouldEndSession(Request $request): bool
    {
        return $request->user() !== null
            && $request->isMethod('GET')
            && $request->routeIs(...self::SCREENS);
    }
}
