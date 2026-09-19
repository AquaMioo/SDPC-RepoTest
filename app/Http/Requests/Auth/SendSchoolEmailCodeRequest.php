<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\SchoolEmailAddress;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The first step of a student sign up: the school address a code goes to.
 *
 * Checked before anything is mailed, so this application never sends mail to
 * an address that could not have become an account — a personal address, or a
 * school address somebody already registered.
 */
class SendSchoolEmailCodeRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     *
     * Compared and stored the one way, trimmed and lowercased, exactly as the
     * full sign up form does in RegisterRequest.
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
        return [
            'school_email' => ['required', 'string', 'max:255', new SchoolEmailAddress, Rule::unique(User::class, 'email')],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'school_email.required' => __('Please enter your school email.'),
            'school_email.unique' => __('An account already uses this school email. Please log in instead.'),
        ];
    }
}
