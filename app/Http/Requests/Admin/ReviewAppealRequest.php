<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewAppealRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['grant', 'deny'])],
            /*
             * Optional when granting — the account is restored and the note
             * would say nothing the status does not. Required when denying,
             * because somebody is being told no and deserves a reason.
             */
            'note' => [Rule::requiredIf($this->input('decision') === 'deny'), 'nullable', 'string', 'max:1000'],
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
            'note.required' => 'Say why the appeal was denied — the account is told this.',
        ];
    }

    /**
     * Determine if the appeal is being granted.
     */
    public function grants(): bool
    {
        return $this->validated('decision') === 'grant';
    }
}
