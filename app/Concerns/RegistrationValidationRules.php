<?php

namespace App\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\SchoolEmailAddress;
use App\Support\PendingGoogleRegistration;
use App\Support\PendingMicrosoftRegistration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The one definition of what a valid sign up looks like.
 *
 * Shared by the request that guards the form and by CreateNewUser, which is
 * the Fortify contract and validates again on its own account — it may be
 * called from a seeder or a test with an array nobody validated.
 */
trait RegistrationValidationRules
{
    use PasswordValidationRules;

    /**
     * Get the validation rules for a sign up.
     *
     * A student signs in with their school address, so a student sign up has
     * no separate email: `school_email` is the account's address and must be
     * a `.edu.ph` one nobody has registered yet.
     *
     * An identity waiting in the session — Google for a client, the school's
     * Microsoft account for a student — supplies the address and stands in for
     * the password, so both stop being required. It is read from the session
     * rather than the form because it is the one thing on that page nobody may
     * edit: an email posted from the browser could be anyone's.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function registrationRules(array $input): array
    {
        $viaGoogle = PendingGoogleRegistration::exists();
        $viaMicrosoft = PendingMicrosoftRegistration::exists();
        $isStudent = $this->isRole($input, UserRole::Student);

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => $viaGoogle || $viaMicrosoft || $isStudent
                ? ['nullable']
                : ['required', 'string', 'email', 'max:255', Rule::unique(User::class), Rule::unique(User::class, 'google_email')],
            'password' => $viaGoogle || $viaMicrosoft ? ['nullable'] : $this->passwordRules(),
            'role' => ['required', Rule::in($this->selfRegistrableRoles($viaGoogle, $viaMicrosoft))],
            'business_name' => [Rule::requiredIf($this->isRole($input, UserRole::Client)), 'nullable', 'string', 'max:255'],
            'school_email' => $isStudent && ! $viaMicrosoft
                ? ['required', 'string', 'max:255', new SchoolEmailAddress, Rule::unique(User::class, 'email')]
                : ['nullable', 'string', 'max:255'],
            'terms' => ['accepted'],
        ];
    }

    /**
     * Get the messages that make the sign up rules readable.
     *
     * @return array<string, string>
     */
    protected function registrationMessages(): array
    {
        return [
            'terms.accepted' => __('You must accept the Terms of Service to create an account.'),
            'business_name.required' => __('Please tell us the name of your business.'),
            'school_email.required' => __('Please enter your school email.'),
            'school_email.unique' => __('An account already uses this school email. Please log in instead.'),
            'role.in' => PendingMicrosoftRegistration::exists()
                ? __('A school Microsoft account can only register a student.')
                : __('A Google account can only register a client. Students sign up with their school email.'),
        ];
    }

    /**
     * Get the roles this sign up may choose between.
     *
     * A Google address is not a school address, so it can only become a
     * client; a school Microsoft account can only become a student.
     *
     * @return array<int, string>
     */
    private function selfRegistrableRoles(bool $viaGoogle, bool $viaMicrosoft): array
    {
        return match (true) {
            $viaMicrosoft => [UserRole::Student->value],
            $viaGoogle => [UserRole::Client->value],
            default => [UserRole::Student->value, UserRole::Client->value],
        };
    }

    /**
     * Determine if the submitted form is registering the given role.
     *
     * @param  array<string, mixed>  $input
     */
    private function isRole(array $input, UserRole $role): bool
    {
        return ($input['role'] ?? null) === $role->value;
    }
}
