<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\StudentVerifier;
use App\Enums\AppealStatus;
use App\Enums\UserRole;
use App\Enums\VerificationProvider;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request, StudentVerifier $verifier): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            /*
             * The optional third-party enrolment check. Absent unless SheerID
             * is configured, and never a gate: what a student may actually do
             * still answers to User::isVerifiedForOperating().
             */
            'studentVerification' => $user?->hasRole(UserRole::Student) && $verifier->isAvailable()
                ? $this->verificationState($user)
                : null,
            /*
             * Account Information → Review Appeal. Only an account with a
             * decision standing against it has anything to answer, so the card
             * is absent rather than empty for everybody else.
             */
            'accountStatus' => [
                'label' => $user->status->label(),
                'restricted' => $user->status->restrictsActions(),
                'mayAppeal' => $user->mayAppeal(),
            ],
            'appeal' => $this->appealState($user),
        ]);
    }

    /**
     * Describe the account's most recent appeal, if it has written one.
     *
     * @return array<string, mixed>|null
     */
    protected function appealState(User $user): ?array
    {
        $appeal = $user->latestAppeal;

        if ($appeal === null) {
            return null;
        }

        return [
            'body' => $appeal->body,
            'statusLabel' => $appeal->status->label(),
            'pending' => ! $appeal->isDecided(),
            'granted' => $appeal->status === AppealStatus::Granted,
            'filedOn' => $appeal->created_at?->toFormattedDateString(),
            'decisionNote' => $appeal->decision_note,
        ];
    }

    /**
     * Describe where the student's optional verification has got to.
     *
     * @return array<string, mixed>
     */
    protected function verificationState(User $student): array
    {
        $verification = $student->studentVerifications
            ->firstWhere('provider', VerificationProvider::SheerId);

        $status = $verification === null
            ? VerificationStatus::Unverified
            : $verification->status;

        return [
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'verifiedAt' => $verification?->verified_at?->toFormattedDateString(),
            'failureReason' => $verification?->failure_reason,
            'hasStarted' => $verification !== null,
        ];
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        /*
         * The upload is pulled out before fill(): `avatar` is a fillable
         * column holding the URL Google supplies, so filling it with an
         * UploadedFile would write an object into a string column.
         */
        $attributes = $request->safe()->except('avatar');

        $user->fill($attributes);

        if ($request->hasFile('avatar')) {
            $replaced = $user->avatar_path;

            $user->avatar_path = $request->file('avatar')->store(
                'avatars/'.$user->id,
                'public',
            );

            // Replacing a picture should not leave the old one on disk.
            if ($replaced !== null) {
                Storage::disk('public')->delete($replaced);
            }
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        /*
         * After the invalidate, not before: clearHistory leaves a flag in the
         * session for the next Inertia response, and invalidating throws an
         * earlier one away. Fortify's logout route does the same thing in
         * App\Http\Responses\LogoutResponse; this is the other way out.
         */
        Inertia::clearHistory();

        return redirect('/');
    }
}
