<?php

namespace App\Http\Requests\Admin;

use App\Enums\VerificationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewBusinessRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // An administrator only ever settles a submission. Unverified and
            // pending are reached by the client uploading, never by a decision.
            'decision' => [
                'required',
                Rule::in([VerificationStatus::Verified->value, VerificationStatus::Rejected->value]),
            ],
        ];
    }

    /**
     * Get the validated decision as an enum.
     */
    public function decision(): VerificationStatus
    {
        return VerificationStatus::from((string) $this->validated('decision'));
    }
}
