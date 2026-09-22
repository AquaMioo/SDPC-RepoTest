<?php

namespace App\Http\Controllers\Settings;

use App\Enums\OneTimePasswordPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SetPasswordRequest;
use App\Services\Verification\OneTimePasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * A first password for an account that has none.
 *
 * Students sign up with a school-email code and no password. The code keeps
 * working, but once they have a password they can also sign in the ordinary
 * way — school email and password on the login form, which already takes a
 * student who has one.
 *
 * There is no current password to confirm, so a code to the account's own
 * address proves it is them (OneTimePasswordPurpose::SetPassword). An account
 * that already has a password changes it under Security instead.
 */
class PasswordSetupController extends Controller
{
    public function __construct(private readonly OneTimePasswordService $passwords) {}

    /**
     * Mail a code to the account's address.
     */
    public function sendCode(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->ensureHasNoPassword($user->password);

        /* False only means one went out moments ago and still works. */
        $this->passwords->send($user->email, OneTimePasswordPurpose::SetPassword);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We sent a code to :email.', ['email' => $user->email])]);

        return back();
    }

    /**
     * Check the code and set the password.
     */
    public function store(SetPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $this->ensureHasNoPassword($user->password);

        $result = $this->passwords->check($user->email, OneTimePasswordPurpose::SetPassword, $request->validated('code'));

        if (! $result->isValid()) {
            throw ValidationException::withMessages(['code' => $result->message()]);
        }

        /* Hashed by the model's cast. */
        $user->forceFill(['password' => $request->validated('password')])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password set. You can now log in with your school email and password.')]);

        return back();
    }

    /**
     * Refuse when there is a password already: that one is changed under
     * Security, which asks for it first.
     */
    protected function ensureHasNoPassword(?string $password): void
    {
        if ($password !== null) {
            throw ValidationException::withMessages([
                'password' => __('Your account already has a password. Change it under Security.'),
            ]);
        }
    }
}
