<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentCredentialRequest extends FormRequest
{
    /**
     * Only students submit credentials.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->requiresCredentialVerification();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'school' => ['required', 'string', Rule::in($this->schools())],
            'document' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:8192',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'school.in' => __('Choose a school from the list.'),
            'document.mimes' => __('Upload a JPG, PNG, WEBP or PDF document.'),
            'document.max' => __('The document must be 8 MB or smaller.'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function schools(): array
    {
        $schools = config('schools.list');

        return is_array($schools) ? array_values(array_filter($schools, 'is_string')) : [];
    }
}
