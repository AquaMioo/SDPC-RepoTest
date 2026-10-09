<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use App\Support\GcashNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Settings → GCash account: the number a client or student registers once for
 * the Project Extension Addendum.
 *
 * Typed any common way (0917 123 4567, +63 917 123 4567) and stored as
 * 09XXXXXXXXX, encrypted (User casts it).
 */
class SaveGcashAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole(UserRole::Client, UserRole::Student);
    }

    /**
     * Bring the number to 09XXXXXXXXX before it is checked.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'gcash_number' => GcashNumber::normalize($this->input('gcash_number')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gcash_number' => ['required', 'string', 'regex:'.GcashNumber::PATTERN],
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
            'gcash_number.required' => __('Enter your GCash number.'),
            'gcash_number.regex' => __('Enter the 11-digit mobile number of your GCash account, starting 09.'),
        ];
    }
}
