<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Rules\SchoolEmailAddress;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Bind a personal Google account to a signed-in student, or take it off again.
 *
 * A school mailbox closes when the student graduates, and with it the address
 * they sign in with. The bound Google account is how they get back in
 * afterwards: ResolveGoogleUser finds an account by its Google id before its
 * address, so the binding keeps working once the school address is dead.
 *
 * Google proves the student holds the account — this runs on the callback of a
 * real consent screen, never on an id or address posted from a form.
 */
class LinkGoogleAccount
{
    public function __construct(private readonly ResolveGoogleUser $resolveGoogleUser) {}

    /**
     * Bind the Google identity to the student, replacing any earlier one.
     *
     * @throws ValidationException
     */
    public function link(User $student, SocialiteUser $googleUser): User
    {
        $googleId = (string) $googleUser->getId();

        if ($googleId === '') {
            $this->refuse(__('Google did not identify that account. Please try again.'));
        }

        $email = $this->resolveGoogleUser->email($googleUser);

        // A school Google account closes at graduation too, so it would fail
        // at the one moment this binding exists for.
        if (SchoolEmailAddress::matches($email)) {
            $this->refuse(__('Link a personal Google account. :email is a school account and will close when you graduate.', [
                'email' => $email,
            ]));
        }

        $boundElsewhere = User::query()
            ->whereKeyNot($student->getKey())
            ->where('google_id', $googleId)
            ->exists();

        if ($boundElsewhere) {
            $this->refuse(__('That Google account is already linked to another SDPC account.'));
        }

        // Signing in with Google also matches on address, so an address that
        // is another account's own would make that sign-in ambiguous.
        $addressTaken = User::query()
            ->whereKeyNot($student->getKey())
            ->where(fn ($query) => $query->where('email', $email)->orWhere('google_email', $email))
            ->exists();

        if ($addressTaken) {
            $this->refuse(__('That Google address belongs to another SDPC account.'));
        }

        $student->forceFill([
            'google_id' => $googleId,
            'google_email' => $email,
            'avatar' => $student->avatar ?? $googleUser->getAvatar(),
        ])->save();

        return $student;
    }

    /**
     * Take the Google account off the student.
     *
     * @throws ValidationException
     */
    public function unlink(User $student): User
    {
        if (! $this->canUnlink($student)) {
            $this->refuse(__('Google is the only way into this account. Set a password with "Forgot password" on the login page first, then remove Google.'));
        }

        $student->forceFill([
            'google_id' => null,
            'google_email' => null,
        ])->save();

        return $student;
    }

    /**
     * Determine if removing Google would still leave the student a way in.
     *
     * A student whose account address is a school address can always sign in
     * with a code mailed to it (StudentCodeLoginController), password or not.
     */
    public function canUnlink(User $student): bool
    {
        return $student->password !== null
            || $student->microsoft_id !== null
            || SchoolEmailAddress::matches($student->email);
    }

    /**
     * @throws ValidationException
     */
    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['google' => [$message]]);
    }
}
