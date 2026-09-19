<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OneTimePasswordPurpose;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\SchoolEmailAddress;
use App\Services\Verification\OneTimePasswordService;
use App\Support\AuthHome;
use App\Support\PendingCodeLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Students sign in with a code mailed to their school address.
 *
 * Students sign up with no password (2026-09-20): the school address proved by
 * a code is the account. So a code to that address is also how they come back
 * — unless they have bound a Google account in settings, which signs them in
 * directly and skips this entirely. Students who made a password before then
 * keep using it on the ordinary form.
 *
 * Nothing here may reveal whether an address has a student account: a code is
 * only mailed when it does, but the screen, the toasts, the resend clock and
 * the rejected-code message are the same either way. See PendingCodeLogin.
 */
class StudentCodeLoginController extends Controller
{
    public function __construct(private readonly OneTimePasswordService $passwords) {}

    /**
     * Mail a code to a student's school address, and show the box it goes in.
     */
    public function send(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        $email = (string) $request->validate([
            'email' => ['required', 'string', 'max:255', new SchoolEmailAddress],
        ])['email'];

        if ($this->student($email) !== null) {
            $this->passwords->send($email, OneTimePasswordPurpose::Login);
        }

        PendingCodeLogin::put($email);

        return to_route('login');
    }

    /**
     * Check the code and sign the student in.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $email = PendingCodeLogin::email();

        if ($email === null) {
            return to_route('login');
        }

        $request->validate(['code' => ['required', 'string']]);

        $result = $this->passwords->check($email, OneTimePasswordPurpose::Login, (string) $request->input('code'));
        $student = $this->student($email);

        /*
         * One message for every refusal. "No code was sent" would be true for
         * exactly the addresses that have no student account.
         */
        if (! $result->isValid() || $student === null) {
            throw ValidationException::withMessages([
                'code' => [__('That code did not work. Check it and try again, or send another.')],
            ]);
        }

        PendingCodeLogin::forget();

        /*
         * The same door the Google button uses: EnforceSingleSession still
         * checks the one-device rule on the way out, and a deactivated account
         * is confined to Settings by ConfineDeactivatedAccounts.
         */
        Auth::login($student, remember: $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(AuthHome::for($student));
    }

    /**
     * Send another code, unless the session says one went out moments ago.
     */
    public function resend(): RedirectResponse
    {
        $email = PendingCodeLogin::email();

        if ($email === null) {
            return to_route('login');
        }

        if (PendingCodeLogin::secondsUntilResend() > 0) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('A code was just sent. Give it a moment before asking for another.'),
            ]);

            return back();
        }

        if ($this->student($email) !== null) {
            $this->passwords->send($email, OneTimePasswordPurpose::Login);
        }

        PendingCodeLogin::restartClock();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('If that school email has a student account, a new code is on its way.'),
        ]);

        return back();
    }

    /**
     * Give up on the code and go back to the ordinary sign in form.
     */
    public function cancel(): RedirectResponse
    {
        $email = PendingCodeLogin::email();

        if ($email !== null) {
            $this->passwords->forget($email, OneTimePasswordPurpose::Login);
        }

        PendingCodeLogin::forget();

        return to_route('login');
    }

    /**
     * The student account at a school address, if there is one.
     */
    private function student(string $email): ?User
    {
        return User::query()
            ->where('email', $email)
            ->where('role', UserRole::Student)
            ->first();
    }
}
