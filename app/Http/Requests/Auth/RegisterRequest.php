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
