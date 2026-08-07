<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveGoogleUser;
use App\Enums\AuthPortal;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Support\AuthHome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * The session key holding the portal the Google flow was started from.
     */
    private const PORTAL_SESSION_KEY = 'auth.google.portal';

    /**
     * The session key holding the role picked on the sign up form.
     */
    private const ROLE_SESSION_KEY = 'auth.google.role';

    public function __construct(private readonly ResolveGoogleUser $resolveGoogleUser) {}

    /**
     * Redirect the user to Google's consent screen.
     *
     * Google only calls back to a single, pre-registered redirect URI, so the
     * portal the flow started from is remembered in the session instead.
     */
    public function redirect(Request $request): SymfonyRedirectResponse
    {
        $this->ensureGoogleIsConfigured();

        $request->session()->put(
            self::PORTAL_SESSION_KEY,
            AuthPortal::fromRequest($request)->value,
        );

        // The sign up form passes the role its toggle is on. It is carried in
        // the session rather than through Google, and re-validated on the way
        // back, so the round trip can never widen it to something else.
        $request->session()->put(
            self::ROLE_SESSION_KEY,
            $this->intendedRole($request)?->value,
        );

        return Socialite::driver('google')->redirect();
    }

    /**
     * Read the requested sign up role, ignoring anything not self-registerable.
     */
    private function intendedRole(Request $request): ?UserRole
    {
        $role = UserRole::tryFrom((string) $request->query('role'));

        return $role?->isSelfRegisterable() ? $role : null;
    }

    /**
     * Authenticate the user from Google's callback.
     */
    public function callback(Request $request): RedirectResponse
    {
        $this->ensureGoogleIsConfigured();

        $portal = AuthPortal::tryFrom(
            (string) $request->session()->pull(self::PORTAL_SESSION_KEY)
        ) ?? AuthPortal::Public;

        $intendedRole = UserRole::tryFrom(
            (string) $request->session()->pull(self::ROLE_SESSION_KEY)
        );

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->failed($portal, __('We could not sign you in with Google. Please try again.'));
        }

        try {
            $user = $this->resolveGoogleUser->handle($googleUser, $portal, $intendedRole);
        } catch (ValidationException $e) {
            return $this->failed($portal, collect($e->errors())->flatten()->first() ?? $e->getMessage());
        }

        $justRegistered = $user->wasRecentlyCreated;

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        // A student signing up through Google goes to the same credential step
        // as one who registered with a password.
        if ($justRegistered && $user->requiresCredentialVerification()) {
            return redirect()->route('credentials.create');
        }

        return redirect()->intended(AuthHome::for($user));
    }

    /**
     * Send the user back to the portal's login screen with an error message.
     */
    private function failed(AuthPortal $portal, string $message): RedirectResponse
    {
        return redirect()
            ->route($portal->loginRouteName())
            ->withErrors(['email' => $message]);
    }

    /**
     * Abort when Google credentials have not been configured for the app.
     */
    private function ensureGoogleIsConfigured(): void
    {
        abort_unless((bool) config('services.google.enabled'), 404);
    }
}
