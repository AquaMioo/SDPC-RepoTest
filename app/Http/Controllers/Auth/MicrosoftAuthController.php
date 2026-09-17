<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveMicrosoftUser;
use App\Http\Controllers\Controller;
use App\Support\AuthHome;
use App\Support\PendingGoogleRegistration;
use App\Support\PendingMicrosoftRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Students signing up and signing in with their school Microsoft account.
 *
 * Schools here run their student mail on Microsoft 365, and Microsoft filters
 * mail from unfamiliar senders hard — a code mailed to a school address often
 * never arrives. Signing in with the school's own account proves the address
 * without sending anything.
 *
 * Public portal only. The admin portal has no Microsoft route at all.
 */
class MicrosoftAuthController extends Controller
{
    /**
     * The session key holding whether the flow was started from sign up.
     */
    private const REGISTERING_SESSION_KEY = 'auth.microsoft.registering';

    /**
     * Error codes Microsoft sends back when the school has not approved the
     * app for its students (AADSTS65001, 90094, 90095).
     */
    private const NEEDS_APPROVAL_CODES = ['AADSTS65001', 'AADSTS90094', 'AADSTS90095'];

    /**
     * Error codes for a personal Microsoft account used against a school-only
     * sign-in (AADSTS50020, 500200).
     */
    private const PERSONAL_ACCOUNT_CODES = ['AADSTS50020', 'AADSTS500200'];

    public function __construct(private readonly ResolveMicrosoftUser $resolveMicrosoftUser) {}

    /**
     * Send the student to Microsoft's sign-in page.
     *
     * `prompt=select_account` because a shared school computer is usually
     * already signed in as somebody else.
     */
    public function redirect(Request $request): SymfonyRedirectResponse
    {
        $this->ensureMicrosoftIsConfigured();

        $request->session()->put(
            self::REGISTERING_SESSION_KEY,
            $request->query('intent') === 'register',
        );

        return Socialite::driver('microsoft')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * Sign the student in, or carry them back to sign up, from Microsoft's
     * callback.
     */
    public function callback(Request $request): RedirectResponse
    {
        $this->ensureMicrosoftIsConfigured();

        $registering = (bool) $request->session()->pull(self::REGISTERING_SESSION_KEY, false);

        // Microsoft reports a refusal as query parameters, not as a code to
        // exchange — cancelled, not approved by the school, wrong account.
        if ($request->filled('error')) {
            return $this->failed($registering, $this->messageForProviderError($request));
        }

        try {
            $driver = Socialite::driver('microsoft');
            $microsoftUser = $driver->user();
            $isPersonalAccount = $driver instanceof MicrosoftProvider && $driver->isConsumerTenant();
        } catch (InvalidStateException $exception) {
            /*
             * Thrown for a stale or forged `state` (the session expired, or
             * the flow was finished in another tab) and, by the Microsoft
             * driver, for an ID token whose signature, issuer, audience or
             * expiry does not check out. The message tells them apart.
             */
            Log::warning('Microsoft sign-in refused.', [
                'reason' => 'invalid_state',
                'message' => Str::limit($exception->getMessage(), 500),
            ]);

            return $this->failed($registering, __('That Microsoft sign-in expired or could not be checked. Please try again.'));
        } catch (Throwable $exception) {
            Log::warning('Microsoft sign-in failed.', [
                'exception' => $exception::class,
                'message' => Str::limit($exception->getMessage(), 500),
            ]);

            return $this->failed($registering, __('We could not sign you in with Microsoft. Please try again.'));
        }

        try {
            $email = $this->resolveMicrosoftUser->email($microsoftUser, $isPersonalAccount);

            if ($registering) {
                return $this->startRegistration($microsoftUser, $email);
            }

            $user = $this->resolveMicrosoftUser->handle($microsoftUser, $email);
        } catch (ValidationException $exception) {
            return $this->failed($registering, (string) collect($exception->errors())->flatten()->first());
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(AuthHome::for($user));
    }

    /**
     * Carry a new school identity back to the sign up form.
     *
     * The address is locked in and needs no code, but the terms still have to
     * be agreed to, so the account is created by the form rather than here.
     *
     * @throws ValidationException
     */
    private function startRegistration(SocialiteUser $microsoftUser, string $email): RedirectResponse
    {
        $this->resolveMicrosoftUser->ensureNotRegistered($microsoftUser, $email);

        // One pending identity at a time: a Google one left over from an
        // earlier attempt would otherwise decide the address instead.
        PendingGoogleRegistration::forget();
        PendingMicrosoftRegistration::put($microsoftUser, $email);

        return redirect()->route('register');
    }

    /**
     * Translate a refusal Microsoft sent back into something a student can act on.
     */
    private function messageForProviderError(Request $request): string
    {
        $error = (string) $request->query('error');
        $description = (string) $request->query('error_description');

        preg_match('/AADSTS\d+/', $description, $match);
        $code = $match[0] ?? null;

        Log::info('Microsoft sign-in refused.', [
            'reason' => 'provider_error',
            'error' => Str::limit($error, 64),
            'code' => $code,
        ]);

        return match (true) {
            in_array($code, self::NEEDS_APPROVAL_CODES, true), $error === 'consent_required' => __('Your school has not approved SDPC for Microsoft sign-in yet. Ask your school\'s IT office to approve it, or sign up with your school email and a password instead.'),
            in_array($code, self::PERSONAL_ACCOUNT_CODES, true) => __('That is a personal Microsoft account. Sign in with the Microsoft account your school gave you.'),
            $error === 'access_denied' => __('Microsoft sign-in was cancelled.'),
            default => __('We could not sign you in with Microsoft. Please try again.'),
        };
    }

    /**
     * Send the student back to the screen they started from, with the error.
     *
     * Keyed on "microsoft" so the screens show it as a banner of its own
     * rather than under the login form's email field.
     */
    private function failed(bool $registering, string $message): RedirectResponse
    {
        return redirect()
            ->route($registering ? 'register' : 'login')
            ->withErrors(['microsoft' => $message]);
    }

    /**
     * Abort when Microsoft credentials have not been configured for the app.
     */
    private function ensureMicrosoftIsConfigured(): void
    {
        abort_unless((bool) config('services.microsoft.enabled'), 404);
    }
}
