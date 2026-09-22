<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a deactivated account inside its settings.
 *
 * A deactivated account still signs in — its appeal is written from Settings
 * → Account, beside the decision it answers — but nothing else is open to it:
 * no dashboard, no board, no messages, no agreements, no teams, no
 * notifications. Anything outside OPEN_ROUTES sends it back to settings.
 *
 * Runs in the web group for every route, so a new module is closed to a
 * deactivated account by default rather than by remembering to gate it.
 */
class ConfineDeactivatedAccounts
{
    /**
     * The routes a deactivated account may still reach, by name.
     *
     * @var list<string>
     */
    private const OPEN_ROUTES = [
        // Public pages.
        'home',
        'legal',
        // Signing in and out. A login screen ends the session on its own
        // (EndSessionOnLoginScreen), and the heartbeat keeps one device per
        // account while settings is open; leave frees it when the tab closes.
        'login',
        'admin.login',
        'logout',
        'session.heartbeat',
        'session.leave',
        // Settings → Account, the appeal, and deleting the account.
        'profile.edit',
        'profile.appeal.store',
        'profile.destroy',
        // Settings → Account → Sign-in methods: a first password.
        'password-setup.code',
        'password-setup.store',
        // Settings → Security.
        'security.edit',
        'user-password.update',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'two-factor.*',
        'passkey.*',
        'well-known.passkeys',
        // Settings → Sign-in methods, for students.
        'student.google.*',
        // Confirming an email address.
        'verification.*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isDeactivated() || $this->isOpen($request, $user)) {
            return $next($request);
        }

        if ($request->header('X-Inertia') === null && $request->expectsJson()) {
            abort(Response::HTTP_FORBIDDEN, __('Your account is deactivated.'));
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('Your account is deactivated. Only Settings and your appeal are available.'),
        ]);

        return redirect()->route('profile.edit');
    }

    /**
     * Determine if the request is one a deactivated account may still make.
     */
    private function isOpen(Request $request, User $user): bool
    {
        // The bare `settings` redirect has no name of its own.
        if ($request->is('settings') || $request->routeIs(...self::OPEN_ROUTES)) {
            return true;
        }

        /*
         * Live updates: only the account's own notification channel, which
         * carries the "someone tried to sign in" alert. Every conversation
         * channel stays closed — a thread closed over HTTP must not be
         * readable over a socket either.
         */
        return $request->is('broadcasting/auth')
            && $request->input('channel_name') === 'private-App.Models.User.'.$user->getKey();
    }
}
