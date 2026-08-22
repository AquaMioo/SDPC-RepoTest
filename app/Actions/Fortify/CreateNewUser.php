<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateTeam;
use App\Concerns\RegistrationValidationRules;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use App\Models\User;
use App\Support\PendingGoogleRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use RegistrationValidationRules;

    public function __construct(private CreateTeam $createTeam) {}

    /**
     * Validate and create a newly registered user.
     *
     * The sign up form lets people pick between the student and client roles.
     * Admin is deliberately not selectable, and the role is assigned here
     * rather than mass assigned, so a crafted request cannot smuggle one in.
     *
     * Every account gets a team, because the whole client module is scoped to
     * one. For a client the team is their business and is named accordingly;
     * for a student it is a personal team they never see.
     *
     * By the time this runs the address has already been proved — either by
     * Google, or by the code RegistrationController made them type. It
     * validates anyway: this is the Fortify contract and may be called with an
     * array nobody checked.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // A Google identity waiting in the session supplies the address and
        // stands in for the password. It is read from the session rather than
        // the form because it is the one thing on that page nobody may edit —
        // an email posted from the browser could be anyone's.
        $pending = PendingGoogleRegistration::get();

        Validator::make(
            $input,
            $this->registrationRules($input),
            $this->registrationMessages(),
        )->validate();

        $email = $pending['email'] ?? $input['email'];

        // The address is unique either way. Checking it here as well covers the
        // window between Google vouching for it and this form being submitted —
        // and, for the code path, the window while the code was in the post.
        if (User::where('email', $email)->exists()) {
            PendingGoogleRegistration::forget();

            throw ValidationException::withMessages([
                'email' => [__('An account already exists for :email. Please log in instead.', ['email' => $email])],
            ]);
        }

        $firstName = trim($input['first_name']);
        $lastName = trim($input['last_name']);
        $role = UserRole::from($input['role']);
        $name = trim($firstName.' '.$lastName);

        return DB::transaction(function () use ($input, $firstName, $lastName, $role, $name, $email, $pending) {
            $user = new User;

            $user->forceFill([
                'name' => $name,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                // A Google account never gets a password. They may still set
                // one later through the password reset flow.
                'password' => $pending !== null ? null : $input['password'],
                'role' => $role,
                'google_id' => $pending['google_id'] ?? null,
                'avatar' => $pending['avatar'] ?? null,
                /*
                 * Nothing reaches this line with an unproved address: Google
                 * vouched for it, or a code sent to it came back. There is no
                 * verification email left to send.
                 */
                'email_verified_at' => now(),
            ])->save();

            PendingGoogleRegistration::forget();

            $team = $this->createTeam->handle(
                $user,
                $role === UserRole::Client ? trim($input['business_name']) : $name."'s Team",
                isPersonal: $role !== UserRole::Client,
            );

            if ($role === UserRole::Client) {
                /*
                 * Businesses arrive verified. Nothing checks a Philippine SME
                 * automatically, and permits are no longer reviewed by hand, so
                 * there is no later step that could grant this — withholding it
                 * would just mean no client could ever post.
                 */
                ClientProfile::create([
                    'team_id' => $team->id,
                    'business_name' => trim($input['business_name']),
                    'owner_name' => $name,
                    'contact_email' => $email,
                    'verification_status' => VerificationStatus::Verified,
                    'verified_at' => now(),
                ]);
            }

            // Students verify with a document after signing up. What they typed
            // here seeds that form so they do not retype it.
            if ($role === UserRole::Student) {
                session()->put('credentials.school', trim($input['school_email']));
            }

            return $user;
        });
    }
}
