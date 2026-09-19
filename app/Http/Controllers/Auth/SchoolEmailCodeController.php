<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OneTimePasswordPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendSchoolEmailCodeRequest;
use App\Services\Verification\OneTimePasswordService;
use App\Support\PendingGoogleRegistration;
use App\Support\PendingMicrosoftRegistration;
use App\Support\PendingRegistration;
use App\Support\PendingSchoolEmailRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The Student tab's first two steps: a school address, then its code.
 *
 * Modelled on the Client tab's "Continue with Google" (2026-09-20). There the
 * identity is settled first and the form then asks only for what Google could
 * not tell us, with no password. Here the code does Google's job: once it comes
 * back, PendingSchoolEmailRegistration holds the proved address and
 * RegistrationController::store() creates the account from the rest of the
 * form without a password or a second code.
 *
 * The registration code purpose is reused on purpose: this IS the code that
 * proves a sign up's address, only asked for earlier.
 */
class SchoolEmailCodeController extends Controller
{
    public function __construct(private readonly OneTimePasswordService $passwords) {}

    /**
     * Mail a code to the school address and show the box it is typed into.
     */
    public function send(SendSchoolEmailCodeRequest $request): RedirectResponse
    {
        $email = (string) $request->validated('school_email');

        /*
         * Starting a student sign up abandons anything else half done in this
         * session. Two identities waiting at once would leave the account's
         * address to whichever one CreateNewUser happened to read first.
         */
        $previous = PendingSchoolEmailRegistration::awaitingEmail();

        if ($previous !== null && $previous !== $email) {
            $this->passwords->forget($previous, OneTimePasswordPurpose::Registration);
        }

        PendingGoogleRegistration::forget();
        PendingMicrosoftRegistration::forget();
        PendingRegistration::forget();

        PendingSchoolEmailRegistration::awaitCode($email);

        /* False only when one went out moments ago and is still good. */
        $this->passwords->send($email, OneTimePasswordPurpose::Registration);

        return to_route('register');
    }

    /**
     * Check the code, and if it holds, let the student finish the form.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $email = PendingSchoolEmailRegistration::awaitingEmail();

        if ($email === null) {
            return to_route('register');
        }

        $request->validate(['code' => ['required', 'string']]);

        $result = $this->passwords->check($email, OneTimePasswordPurpose::Registration, (string) $request->input('code'));

        /*
         * The specific message is safe here, unlike on the sign in and appeal
         * screens: a code is always sent at sign up, so "expired" or "wrong"
         * says nothing about whether an account exists.
         */
        if (! $result->isValid()) {
            throw ValidationException::withMessages(['code' => [$result->message()]]);
        }

        PendingSchoolEmailRegistration::markVerified();

        return to_route('register');
    }

    /**
     * Send another code, unless one went out moments ago.
     */
    public function resend(): RedirectResponse
    {
        $email = PendingSchoolEmailRegistration::awaitingEmail();

        if ($email === null) {
            return to_route('register');
        }

        $sent = $this->passwords->send($email, OneTimePasswordPurpose::Registration);

        Inertia::flash('toast', $sent
            ? ['type' => 'success', 'message' => __('A new code is on its way.')]
            : ['type' => 'error', 'message' => __('A code was just sent. Give it a moment before asking for another.')],
        );

        return back();
    }
}
