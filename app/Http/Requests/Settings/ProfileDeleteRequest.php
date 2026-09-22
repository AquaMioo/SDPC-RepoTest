<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * An account with a password confirms with it. One made by a school-email
     * code or through Google has none, so it confirms with the code mailed by
     * AccountDeletionCodeController instead; ProfileController::destroy checks
     * that code.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->user()->password === null) {
            return [
                'code' => ['required', 'string', 'max:12'],
            ];
        }

        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
