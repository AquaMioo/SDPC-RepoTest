<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateTeam;
use App\Concerns\RegistrationValidationRules;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use App\Models\User;
use App\Services\Verification\SchoolEmailVerifier;
use App\Support\PendingGoogleRegistration;
use App\Support\PendingMicrosoftRegistration;
use App\Support\PendingSchoolEmailRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use RegistrationValidationRules;

    public function __construct(
        private CreateTeam $createTeam,
        private SchoolEmailVerifier $schoolEmailVerifier,
    ) {}

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
     * A student's account address is their school address. By the time this
     * runs the address has already been proved — by Google or the school's
     * Microsoft sign-in, or by the code RegistrationController made them type.
     * It validates anyway: this is the Fortify contract and may be called with
     * an array nobody checked.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // An identity waiting in the session supplies the address and stands
        // in for the password. It is read from the session rather than the
        // form because it is the one thing on that page nobody may edit — an
        // email posted from the browser could be anyone's.
        $google = PendingGoogleRegistration::get();
        $microsoft = PendingMicrosoftRegistration::get();
        // A school address proved by a code on the Student tab.
        $schoolEmail = PendingSchoolEmailRegistration::verifiedEmail();

        Validator::make(
            $input,
            $this->registrationRules($input),
            $this->registrationMessages(),
        )->validate();

        $role = UserRole::from($input['role']);

        $email = $microsoft['email']
            ?? $schoolEmail
            ?? $google['email']
            ?? ($role === UserRole::Student
                ? mb_strtolower(trim($input['school_email']))
                : $input['email']);

        // The address is unique either way. Checking it here as well covers the
        // window between Google or Microsoft vouching for it and this form being
        // submitted — and, for the code path, the window while the code was in
        // the post.
        if (User::where('email', $email)->exists()) {
            PendingGoogleRegistration::forget();
            PendingMicrosoftRegistration::forget();
            PendingSchoolEmailRegistration::forget();

            throw ValidationException::withMessages([
                'email' => [__('An account already exists for :email. Please log in instead.', ['email' => $email])],
            ]);
        }

        $firstName = trim($input['first_name']);
        $lastName = trim($input['last_name']);
        $name = trim($firstName.' '.$lastName);

        return DB::transaction(function () use ($input, $firstName, $lastName, $role, $name, $email, $google, $microsoft, $schoolEmail) {
            $user = new User;

            $user->forceFill([
                'name' => $name,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                // An account made through Google, Microsoft or a school-email
                // code never gets a password. A student signs back in with a
                // code or a bound Google account; anyone may still set one
                // through the password reset flow.
                'password' => $google !== null || $microsoft !== null || $schoolEmail !== null ? null : $input['password'],
                'role' => $role,
                'google_id' => $google['google_id'] ?? null,
                'google_email' => $google['email'] ?? null,
                'microsoft_id' => $microsoft['microsoft_id'] ?? null,
                'avatar' => $google['avatar'] ?? null,
                /*
                 * Nothing reaches this line with an unproved address: Google or
                 * Microsoft vouched for it, or a code sent to it came back.
                 * There is no verification email left to send.
                 */
                'email_verified_at' => now(),
            ])->save();

            PendingGoogleRegistration::forget();
            PendingMicrosoftRegistration::forget();
            PendingSchoolEmailRegistration::forget();

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

            if ($role === UserRole::Student) {
                // Seeds the credential form so the school is not retyped.
                session()->put('credentials.school', $email);

                /*
                 * The address was just proved, so a student on a listed school
                 * domain is recorded as verified now — switching the
                 * school-email check on later must not lock them out.
                 */
                $this->schoolEmailVerifier->confirmAtSignUp($user, $email);
            }

            return $user;
        });
    }
}
