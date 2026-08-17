<?php

namespace App\Http\Controllers\Student;

use App\Contracts\StudentVerifier;
use App\Enums\VerificationProvider;
use App\Http\Controllers\Controller;
use App\Models\StudentVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The optional third-party enrolment check.
 *
 * Both routes 404 unless a verifier is actually configured, so nothing is
 * reachable — or advertised — on a normal boot. Whatever the answer turns out
 * to be, it changes nothing about what the student may do: applying, messaging
 * and signing all still wait on the credential an administrator reviews.
 */
class StudentVerificationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private StudentVerifier $verifier) {}

    /**
     * Start a verification and send the student to the provider's own form.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->verifier->isAvailable(), 404);

        $verification = $this->verifier->start($request->user());

        if ($verification === null || blank($verification->redirect_url)) {
            return back()->with(
                'error',
                __('Student verification is unavailable right now. Nothing on your account depends on it.'),
            );
        }

        return redirect()->away($verification->redirect_url);
    }

    /**
     * Ask the provider where the check got to.
     *
     * This is what the student comes back to from the hosted form. It is a
     * poll rather than a webhook on purpose: an endpoint a stranger can post a
     * "verified" to is worse than no verification at all.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->verifier->isAvailable(), 404);

        $verification = StudentVerification::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', VerificationProvider::SheerId)
            ->first();

        abort_if($verification === null, 404);

        $this->verifier->refresh($verification);

        return redirect()
            ->route('profile.edit')
            ->with('success', __('Your verification status has been updated.'));
    }
}
