<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Enums\UserRole;
use App\Models\ClientProfile;
use App\Models\User;
use App\Support\PendingGoogleRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

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
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // A Google identity waiting in the session supplies the address and
        // stands in for the password. It is read from the session rather than
        // the form because it is the one thing on that page nobody may edit —
        // an email posted from the browser could be anyone's.
        $pending = PendingGoogleRegistration::get();

        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => $pending !== null
                ? ['nullable']
                : ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $pending !== null ? ['nullable'] : $this->passwordRules(),
            'role' => ['required', Rule::in([UserRole::Student->value, UserRole::Client->value])],
            'business_name' => [Rule::requiredIf($this->isClient($input)), 'nullable', 'string', 'max:255'],
            'school_email' => [Rule::requiredIf($this->isStudent($input)), 'nullable', 'string', 'max:255'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('You must accept the Terms of Service to create an account.'),
            'business_name.required' => __('Please tell us the name of your business.'),
            'school_email.required' => __('Please provide your school email or student number.'),
        ])->validate();

        $email = $pending['email'] ?? $input['email'];

        // The address is unique either way. Checking it here as well covers the
        // window between Google vouching for it and this form being submitted.
        if ($pending !== null && User::where('email', $email)->exists()) {
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
                // Google has already proven the address, so there is nothing
                // for the verification email to add.
                'email_verified_at' => $pending !== null ? now() : null,
            ])->save();

            PendingGoogleRegistration::forget();

            $team = $this->createTeam->handle(
                $user,
                $role === UserRole::Client ? trim($input['business_name']) : $name."'s Team",
                isPersonal: $role !== UserRole::Client,
            );

            if ($role === UserRole::Client) {
                ClientProfile::create([
                    'team_id' => $team->id,
                    'business_name' => trim($input['business_name']),
                    'owner_name' => $name,
                    'contact_email' => $input['email'],
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

    /**
     * Determine if the submitted form is registering a client account.
     *
     * @param  array<string, string>  $input
     */
    private function isClient(array $input): bool
    {
        return ($input['role'] ?? null) === UserRole::Client->value;
    }

    /**
     * Determine if the submitted form is registering a student account.
     *
     * @param  array<string, string>  $input
     */
    private function isStudent(array $input): bool
    {
        return ($input['role'] ?? null) === UserRole::Student->value;
    }
}
