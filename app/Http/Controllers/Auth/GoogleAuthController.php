<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveGoogleUser;
use App\Enums\AuthPortal;
use App\Enums\GoogleAuthIntent;
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
     * The session key holding which button started the flow.
     */
    private const INTENT_SESSION_KEY = 'auth.google.intent';

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

        // Which button was pressed rides in the session for the same reason as
        // the portal: Google hands back nothing we could infer it from.
        $request->session()->put(
            self::INTENT_SESSION_KEY,
            GoogleAuthIntent::fromRequest($request)->value,
        );

        return Socialite::driver('google')->redirect();
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

        $intent = GoogleAuthIntent::tryFrom(
            (string) $request->session()->pull(self::INTENT_SESSION_KEY)
        ) ?? GoogleAuthIntent::Login;

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->failed($portal, $intent, __('We could not sign you in with Google. Please try again.'));
        }

        try {
            $user = $this->resolveGoogleUser->handle($googleUser, $portal, $intent);
        } catch (ValidationException $e) {
            return $this->failed($portal, $intent, collect($e->errors())->flatten()->first() ?? $e->getMessage());
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(AuthHome::for($user));
    }

    /**
     * Send the user back to the screen they started from, with the error.
     *
     * Someone who pressed "Continue with Google" on the sign up screen is
     * returned there rather than to the login screen, so the message lands
     * next to the form they still need to fill in.
     */
    private function failed(AuthPortal $portal, GoogleAuthIntent $intent, string $message): RedirectResponse
    {
        $route = $intent === GoogleAuthIntent::Register && $portal === AuthPortal::Public
            ? 'register'
            : $portal->loginRouteName();

        // Keyed on "google" rather than "email": this failure belongs to the
        // Google button, not to the login form's email field, and the screens
        // surface it as a banner of its own.
        return redirect()
            ->route($route)
            ->withErrors(['google' => $message]);
    }

    /**
     * Abort when Google credentials have not been configured for the app.
     */
    private function ensureGoogleIsConfigured(): void
    {
        abort_unless((bool) config('services.google.enabled'), 404);
    }
}
