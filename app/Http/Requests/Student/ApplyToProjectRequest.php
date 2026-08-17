<?php

namespace App\Http\Requests\Student;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApplyToProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The route already carries the student and verification middleware; this
     * is the belt to that pair of braces.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Student) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cover_letter' => ['required', 'string', 'min:40', 'max:2000'],
            'proposed_rate' => ['nullable', 'integer', 'min:0', 'max:1000000'],
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
            'cover_letter.required' => 'Tell the client why you are a fit.',
            'cover_letter.min' => 'Give the client something to read — at least 40 characters.',
        ];
    }
}
