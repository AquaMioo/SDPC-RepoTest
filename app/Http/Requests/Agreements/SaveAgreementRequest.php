<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveAgreementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');

        return $agreement instanceof Agreement && Gate::allows('update', $agreement);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Milestones arrive whole rather than one at a time: reordering, renaming
     * and repricing are one negotiation, and applying them piecemeal would let
     * a half-saved schedule reach the other party.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scope_summary' => ['required', 'string', 'max:5000'],
            'deliverables' => ['array', 'max:20'],
            'deliverables.*' => ['required', 'string', 'max:500'],

            'intellectual_property_terms' => ['required', 'string', 'max:5000'],
            'confidentiality_terms' => ['required', 'string', 'max:5000'],
            'academic_terms' => ['required', 'string', 'max:5000'],

            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],

            'milestones' => ['required', 'array', 'min:1', 'max:12'],
            'milestones.*.id' => ['nullable', 'integer'],
            'milestones.*.title' => ['required', 'string', 'max:120'],
            'milestones.*.description' => ['nullable', 'string', 'max:2000'],
            /* Whole pesos, and a ceiling that stops a typo becoming a contract. */
            'milestones.*.amount' => ['required', 'integer', 'min:0', 'max:10000000'],
            'milestones.*.starts_on' => ['nullable', 'date'],
            'milestones.*.ends_on' => ['nullable', 'date', 'after_or_equal:milestones.*.starts_on'],
        ];
    }

    /**
     * Get the human-readable names for the validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scope_summary' => 'scope',
            'milestones.*.amount' => 'milestone amount',
            'milestones.*.title' => 'milestone title',
            'milestones.*.ends_on' => 'milestone end date',
        ];
    }
}
