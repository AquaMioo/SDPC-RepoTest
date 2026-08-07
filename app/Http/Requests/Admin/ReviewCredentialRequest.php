<?php

namespace App\Http\Requests\Admin;

use App\Enums\CredentialStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCredentialRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // An administrator only ever settles a submission; they cannot move
            // one back into the automated states.
            'decision' => [
                'required',
                Rule::in([CredentialStatus::Verified->value, CredentialStatus::Rejected->value]),
            ],
            'reason' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf($this->input('decision') === CredentialStatus::Rejected->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('Give the student a reason for the rejection.'),
        ];
    }

    /**
     * Get the validated decision as an enum.
     */
    public function decision(): CredentialStatus
    {
        return CredentialStatus::from((string) $this->validated('decision'));
    }
}
