<?php

namespace App\Http\Controllers\Settings;

use App\Enums\OneTimePasswordPurpose;
use App\Http\Controllers\Controller;
use App\Services\Verification\OneTimePasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The code that confirms deleting an account with no password.
 *
 * Deleting asks for the current password, and accounts made by a school-email
 * code or through Google never had one, so they could not leave at all. A code
 * to the account's own address proves it is them instead
 * (OneTimePasswordPurpose::DeleteAccount); ProfileController::destroy checks it.
 */
class AccountDeletionCodeController extends Controller
{
    public function __construct(private readonly OneTimePasswordService $passwords) {}

    /**
     * Mail a code to the account's address.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->password !== null) {
            throw ValidationException::withMessages([
                'code' => __('Your account has a password. Enter it to delete the account.'),
            ]);
        }

        /* False only means one went out moments ago and still works. */
        $this->passwords->send($user->email, OneTimePasswordPurpose::DeleteAccount);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We sent a code to :email.', ['email' => $user->email])]);

        return back();
    }
}
