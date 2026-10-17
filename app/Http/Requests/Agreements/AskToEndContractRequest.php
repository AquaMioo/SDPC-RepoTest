<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A denial or a termination request: the reason is required (owner,
 * 2026-10-18). A client asking to terminate also rates the work, because the
 * collaboration completes once the student agrees.
 */
class AskToEndContractRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $agreement = $this->route('agreement');
        $clientTerminating = $this->routeIs('agreements.terminations.store')
            && $agreement instanceof Agreement
            && $this->user()?->belongsToTeam($agreement->team);

        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'rating' => $clientTerminating ? ['required', 'integer', 'between:1,5'] : ['prohibited'],
            'feedback' => $clientTerminating ? ['nullable', 'string', 'max:2000'] : ['prohibited'],
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
            'reason.required' => __('Give a reason. The other side reads it before answering.'),
            'reason.min' => __('Give a reason. The other side reads it before answering.'),
            'rating.required' => __('Rate the work (1 to 5 stars). Ending the collaboration completes the project.'),
        ];
    }
}
