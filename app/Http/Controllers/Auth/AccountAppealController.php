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
 * been deactivated. That is harder than it looks, because a code is only
 * really sent when there is something to appeal: every observable this page
 * produces has to be built from the session rather than from whether a code
 * row exists, or the difference shows through. See secondsUntilResend() and
 * CODE_REJECTED for the two places it nearly did.
 */
class AccountAppealController extends Controller
{
    /** Where the address being appealed for waits between the two steps. */
    private const SESSION_KEY = 'appeal.email';

    /**
     * When this flow last told somebody a code was on its way.
     *
     * Recorded whether or not an email actually went out. The resend cooldown
     * is measured from this rather than from the code row, because only an
     * address with something to appeal has a code row — reading the cooldown
     * off it put a 60-second countdown on the resend button for exactly those
     * addresses and left it enabled for every other one, which told anybody
     * watching the button which was which.
     */
    private const SENT_AT_KEY = 'appeal.code_sent_at';

    /**
     * The one answer every rejected code gets here.
     *
     * OneTimePasswordResult tells its failures apart so a screen can say
     * something useful, and for registration that is safe: a code was
     * definitely sent, so "expired" and "incorrect" are both about a code the
     * person really has. On this page nothing is sent unless there is
     * something to appeal, which makes "no code on file" and "wrong guess"
     * the same question as "does this address have an account". So they read
     * identically, at the deliberate cost of a vaguer message.
     */
    private const CODE_REJECTED = 'That code is incorrect or has expired. Ask for a new one.';

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
            'secondsUntilResend' => $this->secondsUntilResend($request),
        ]);
    }

    /**
     * Send a code to the address, if there is anything there to appeal.
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
        $request->session()->put(self::SENT_AT_KEY, now()->getTimestamp());

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
            throw ValidationException::withMessages(['code' => [__(self::CODE_REJECTED)]]);
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
     *
     * Which of the two answers comes back is decided by the session clock
     * alone, never by whether there was anything to send to. Deciding it on
     * the send itself said "a new code is on its way" only for addresses with
     * an account under review, which is the one thing this page must not say.
     */
    public function resend(Request $request): RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_KEY);

        if (! is_string($email)) {
            return to_route('appeal');
        }

        $waiting = $this->secondsUntilResend($request) > 0;

        if (! $waiting) {
            $request->session()->put(self::SENT_AT_KEY, now()->getTimestamp());

            if ($this->appealableAccount($email) !== null) {
                $this->passwords->send($email, OneTimePasswordPurpose::Appeal);
            }
        }

        Inertia::flash('toast', $waiting
            ? ['type' => 'error', 'message' => __('A code was just sent. Give it a moment before asking for another.')]
            : ['type' => 'success', 'message' => __('A new code is on its way.')],
        );

        return back();
    }

    /**
     * How long this page says it will be before another code may be asked for.
     *
     * Read from the session, so an address with nothing to appeal counts down
     * exactly like one that really was sent a code.
     */
    private function secondsUntilResend(Request $request): int
    {
        $sentAt = $request->session()->get(self::SENT_AT_KEY);

        if (! is_int($sentAt)) {
            return 0;
        }

        return max(0, $sentAt + (int) config('otp.resend_after') - now()->getTimestamp());
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
        $request->session()->forget([self::SESSION_KEY, self::SENT_AT_KEY]);

        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('login');
    }
}
