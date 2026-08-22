<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Appeals\FileAppeal;
use App\Enums\OneTimePasswordPurpose;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Verification\OneTimePasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The appeal page for accounts that can no longer sign in.
 *
 * A deactivated account cannot reach settings, so it cannot use the appeal
 * that lives there — and it is the account most likely to want one. This is
 * that door, and it is open to guests by necessity.
 *
 * Identity is proved with an emailed code rather than a password, for a reason
 * that is easy to miss: accounts created through Google have no password at
 * all (App\Actions\Fortify\AuthenticateUser refuses them on the password
 * form), so asking for one would lock out exactly the people it was meant to
 * let in. It is the same code machinery registration uses, under a different
 * purpose so neither can be replayed as the other.
 *
 * Nothing here says whether an address has an account. Every answer is the
 * same, so this page cannot be used to find out who is registered — or who has
 * been deactivated.
 */
class AccountAppealController extends Controller
{
    /** Where the address being appealed for waits between the two steps. */
    private const SESSION_KEY = 'appeal.email';

    public function __construct(
        private readonly OneTimePasswordService $passwords,
        private readonly FileAppeal $fileAppeal,
    ) {}

    /**
     * Show the appeal page.
     */
    public function create(Request $request): Response
    {
        $email = $request->session()->get(self::SESSION_KEY);

        return Inertia::render('auth/appeal', [
            'email' => $email,
            'codeLength' => (int) config('otp.length'),
            'expiresAfter' => (int) config('otp.expires_after'),
            'secondsUntilResend' => is_string($email)
                ? $this->passwords->secondsUntilResend($email, OneTimePasswordPurpose::Appeal)
                : 0,
        ]);
    }

    /**
     * Send a code to the address, if there is anything there to appeal for.
     *
     * The response is identical either way. Only an account that actually has
     * a decision standing against it gets an email — appealing an approved
     * account would produce a row no administrator could act on — but the page
     * moves to the code step regardless, so nothing is learned from it.
     */
    public function sendCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = mb_strtolower(trim($validated['email']));

        if ($this->appealableAccount($email) !== null) {
            $this->passwords->send($email, OneTimePasswordPurpose::Appeal);
        }

        $request->session()->put(self::SESSION_KEY, $email);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('If that address has an account under review, a code is on its way to it.'),
        ]);

        return to_route('appeal');
    }

    /**
     * Check the code and record the appeal.
     */
    public function store(Request $request): RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_KEY);

        if (! is_string($email)) {
            return to_route('appeal');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'body' => ['required', 'string', 'min:30', 'max:2000'],
        ], [
            'body.min' => 'Please explain your side in at least 30 characters, so an administrator has something to weigh.',
        ]);

        $result = $this->passwords->check(
            $email,
            OneTimePasswordPurpose::Appeal,
            (string) $validated['code'],
        );

        if (! $result->isValid()) {
            throw ValidationException::withMessages(['code' => [$result->message()]]);
        }

        $user = $this->appealableAccount($email);

        /*
         * A correct code for an address with nothing to appeal. Reachable only
         * if the account's status changed while the code was in the post, and
         * answered the same way as everything else on this page.
         */
        if ($user === null) {
            return $this->finish($request, __('There is nothing to appeal on that account.'), 'error');
        }

        $appeal = $this->fileAppeal->handle($user, (string) $validated['body']);

        return $appeal === null
            ? $this->finish($request, __('An appeal from this account is already waiting for review.'), 'error')
            : $this->finish($request, __('Appeal submitted. An administrator will review it and contact you by email.'));
    }

    /**
     * Send another code, unless one went out moments ago.
     */
    public function resend(Request $request): RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_KEY);

        if (! is_string($email)) {
            return to_route('appeal');
        }

        $sent = $this->appealableAccount($email) !== null
            && $this->passwords->send($email, OneTimePasswordPurpose::Appeal);

        Inertia::flash('toast', $sent
            ? ['type' => 'success', 'message' => __('A new code is on its way.')]
            : ['type' => 'error', 'message' => __('A code was just sent. Give it a moment before asking for another.')],
        );

        return back();
    }

    /**
     * Find the account at this address that has something to appeal.
     *
     * Null covers all three of "no such account", "nothing standing against
     * it" and "administrator", and the caller treats them identically — the
     * whole point being that the page's answers never differ.
     */
    private function appealableAccount(string $email): ?User
    {
        $user = User::firstWhere('email', $email);

        return $user?->mayAppeal() ? $user : null;
    }

    /**
     * Close the flow out, whatever the outcome.
     */
    private function finish(Request $request, string $message, string $type = 'success'): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('login');
    }
}
