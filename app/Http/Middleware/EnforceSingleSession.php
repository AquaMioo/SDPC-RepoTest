<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AccountSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns away every session that does not hold its account.
 *
 * Checked twice, because an account can be entered two ways. A request that
 * arrives signed in is checked before anything else runs. A request that signs
 * somebody in — the login form, the two-factor challenge, the Google callback,
 * finishing a registration — is checked after, and its response is replaced,
 * so the refused device never even sees the dashboard redirect.
 *
 * First in the web group on purpose. Everything after it — the login screen's
 * own logout, the Inertia share, the presence stamp — may only ever see the
 * holder. TouchLastSeen in particular: a refused device that got to stamp the
 * account would keep it looking busy and lock the real holder out with it.
 *
 * The refusal signs out with logoutCurrentDevice(), not logout(). logout()
 * fires the Logout event, whose listener clears the presence stamp — which
 * would free the account for the very device being turned away — and cycles
 * the remember token, which would sign the holder out of "remember me".
 */
class EnforceSingleSession
{
    public function __construct(private readonly AccountSession $accountSession) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.single_session')) {
            return $next($request);
        }

        $guard = Auth::guard((string) config('fortify.guard'));

        $user = $guard->user();

        if ($user instanceof User) {
            $refusal = $this->accountSession->admit($request, $user);

            return $refusal === null
                ? $next($request)
                : $this->turnAway($request, $user, $refusal);
        }

        $response = $next($request);

        $user = $guard->user();

        if ($user instanceof User) {
            $refusal = $this->accountSession->admit($request, $user);

            if ($refusal !== null) {
                return $this->turnAway($request, $user, $refusal);
            }
        }

        return $response;
    }

    /**
     * Sign this device out and send it to its portal's login screen.
     *
     * The order matches App\Http\Responses\LogoutResponse: the warning and the
     * history flag both live in the session, so they are written after it has
     * been invalidated or they would be thrown away with it.
     *
     * The heartbeat asks for JSON and cannot follow a redirect into a page, so
     * it is told where to go instead. Inertia's own visits ask for HTML and
     * follow the redirect as usual — always a 303, because this runs outside
     * HandleInertiaRequests, which is what normally turns the 302 after a PUT
     * or DELETE into one, and a 302 there is replayed with the same method.
     */
    private function turnAway(Request $request, User $user, string $message): Response
    {
        Auth::guard((string) config('fortify.guard'))->logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Inertia::clearHistory();

        $request->session()->flash('warning', $message);

        $login = $user->isAdmin() ? route('admin.login') : route('login');

        if (! $request->hasHeader('X-Inertia') && $request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => $login], Response::HTTP_CONFLICT);
        }

        return redirect()->to($login, Response::HTTP_SEE_OTHER);
    }
}
