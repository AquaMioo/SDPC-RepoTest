<?php

namespace App\Http\Controllers\Student;

use App\Actions\Auth\LinkGoogleAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Settings → Profile → Sign-in methods: a personal Google account for later.
 *
 * Signed-in students only, on a callback of its own. The guest-only
 * auth/google/callback would send a signed-in student straight back to their
 * dashboard before it ran, so this one has to be registered in Google Cloud as
 * a second authorised redirect URI.
 */
class LinkedGoogleAccountController extends Controller
{
    public function __construct(private readonly LinkGoogleAccount $linkGoogleAccount) {}

    /**
     * Send the student to Google to pick the account to bind.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        $this->ensureGoogleIsConfigured();

        // Always ask which account: the browser is usually signed in to the
        // school's Google or somebody else's already.
        return $this->driver()
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * Bind the account Google hands back.
     */
    public function callback(Request $request): RedirectResponse
    {
        $this->ensureGoogleIsConfigured();

        if ($request->filled('error')) {
            return $this->failed($request->query('error') === 'access_denied'
                ? __('Linking Google was cancelled.')
                : __('We could not link your Google account. Please try again.'));
        }

        try {
            $googleUser = $this->driver()->user();
        } catch (InvalidStateException) {
            return $this->failed(__('That Google sign-in expired. Please try again.'));
        } catch (Throwable $exception) {
            Log::warning('Linking a Google account failed.', [
                'exception' => $exception::class,
                'message' => Str::limit($exception->getMessage(), 500),
            ]);

            return $this->failed(__('We could not link your Google account. Please try again.'));
        }

        try {
            $student = $this->linkGoogleAccount->link($request->user(), $googleUser);
        } catch (ValidationException $exception) {
            return $this->failed((string) collect($exception->errors())->flatten()->first());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Google account :email linked. You can sign in with it from now on.', [
                'email' => $student->google_email,
            ]),
        ]);

        return to_route('profile.edit');
    }

    /**
     * Take the Google account off.
     */
    public function destroy(Request $request): RedirectResponse
    {
        try {
            $this->linkGoogleAccount->unlink($request->user());
        } catch (ValidationException $exception) {
            return $this->failed((string) collect($exception->errors())->flatten()->first());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Google account removed.')]);

        return to_route('profile.edit');
    }

    /**
     * The Google driver, pointed at this controller's own callback.
     */
    private function driver(): AbstractProvider
    {
        return Socialite::driver('google')->redirectUrl(route('student.google.callback'));
    }

    /**
     * Return to settings with the reason, as a toast.
     */
    private function failed(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return to_route('profile.edit');
    }

    /**
     * Abort when Google credentials have not been configured for the app.
     */
    private function ensureGoogleIsConfigured(): void
    {
        abort_unless((bool) config('services.google.enabled'), 404);
    }
}
