<?php

namespace App\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PendingGoogleRegistration;
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
     * A Google identity waiting in the session supplies the address and stands
     * in for the password, so both stop being required. It is read from the
     * session rather than the form because it is the one thing on that page
     * nobody may edit — an email posted from the browser could be anyone's.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function registrationRules(array $input): array
    {
        $viaGoogle = PendingGoogleRegistration::exists();

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => $viaGoogle
                ? ['nullable']
                : ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $viaGoogle ? ['nullable'] : $this->passwordRules(),
            'role' => ['required', Rule::in([UserRole::Student->value, UserRole::Client->value])],
            'business_name' => [Rule::requiredIf($this->isRole($input, UserRole::Client)), 'nullable', 'string', 'max:255'],
            'school_email' => [Rule::requiredIf($this->isRole($input, UserRole::Student)), 'nullable', 'string', 'max:255'],
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
            'school_email.required' => __('Please provide your school email or student number.'),
        ];
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
