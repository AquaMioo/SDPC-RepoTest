<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Auth\LinkGoogleAccount;
use App\Enums\AppealStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Rules\SchoolEmailAddress;
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
    public function edit(Request $request, LinkGoogleAccount $linkGoogleAccount): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            /*
             * Students only: how they get in now, and the personal Google
             * account that gets them in once the school address is gone.
             */
            'signInMethods' => $user->hasRole(UserRole::Student)
                ? $this->signInMethods($user, $linkGoogleAccount)
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
     * Describe the ways a student can sign in.
     *
     * @return array{hasPassword: bool, schoolEmailCode: bool, microsoftAvailable: bool, microsoftLinked: bool, googleAvailable: bool, googleEmail: string|null, googleLinked: bool, canUnlinkGoogle: bool}
     */
    protected function signInMethods(User $student, LinkGoogleAccount $linkGoogleAccount): array
    {
        return [
            'hasPassword' => $student->password !== null,
            // A school address can always be sent a sign in code.
            'schoolEmailCode' => SchoolEmailAddress::matches($student->email),
            // So the card stops pointing at a button the login page is not showing.
            'microsoftAvailable' => (bool) config('services.microsoft.enabled'),
            'microsoftLinked' => $student->microsoft_id !== null,
            'googleAvailable' => (bool) config('services.google.enabled'),
            'googleLinked' => $student->google_id !== null,
            'googleEmail' => $student->google_id !== null ? $student->google_email : null,
            'canUnlinkGoogle' => $linkGoogleAccount->canUnlink($student),
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
