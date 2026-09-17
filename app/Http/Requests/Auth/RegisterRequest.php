<?php

namespace App\Http\Requests\Auth;

use App\Concerns\RegistrationValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The sign up form, checked before a code is sent anywhere.
 *
 * Validating first matters: mailing a code to an address on a form that was
 * going to be rejected anyway is both a wasted email and a way to have this
 * application send mail to any address somebody names.
 */
class RegisterRequest extends FormRequest
{
    use RegistrationValidationRules;

    /**
     * Prepare the data for validation.
     *
     * The school address becomes the account's sign-in address, so it is
     * compared and stored the one way — trimmed and lowercased — whatever the
     * database's collation thinks of case.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('school_email'))) {
            $this->merge(['school_email' => mb_strtolower(trim($this->input('school_email')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return $this->registrationRules($this->all());
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->registrationMessages();
    }
}
