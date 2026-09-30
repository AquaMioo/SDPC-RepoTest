<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use Throwable;

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

            /*
             * The earlier clauses. A Memorandum of Agreement does not show or
             * edit them, so the form may leave them out.
             */
            'intellectual_property_terms' => ['nullable', 'string', 'max:5000'],
            'confidentiality_terms' => ['nullable', 'string', 'max:5000'],
            'academic_terms' => ['nullable', 'string', 'max:5000'],

            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],

            'milestones' => ['required', 'array', 'min:1', 'max:12'],
            'milestones.*.id' => ['nullable', 'integer'],
            'milestones.*.title' => ['required', 'string', 'max:120'],
            'milestones.*.description' => ['nullable', 'string', 'max:2000'],
            /* Whole pesos, and a ceiling that stops a typo becoming a contract. */
            'milestones.*.amount' => ['required', 'integer', 'min:0', 'max:10000000'],
            /* Work starts today or later — never in the past. */
            'milestones.*.starts_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'milestones.*.ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:milestones.*.starts_on'],
        ];
    }

    /**
     * Keep the whole timeline within one year.
     *
     * The phases are the timeline the client draws: work starts on the
     * earliest phase start and is complete on the latest phase end. More than
     * a year between the two is not a capstone term, and is almost always a
     * mistyped year.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $dates = collect($this->input('milestones', []))
                    ->filter(fn (mixed $milestone): bool => is_array($milestone));

                $starts = $dates->pluck('starts_on')->filter(fn (mixed $date): bool => is_string($date) && $date !== '');
                $ends = $dates->pluck('ends_on')->filter(fn (mixed $date): bool => is_string($date) && $date !== '');

                if ($starts->isEmpty() || $ends->isEmpty()) {
                    return;
                }

                try {
                    $start = Carbon::createFromFormat('Y-m-d', (string) $starts->min());
                    $end = Carbon::createFromFormat('Y-m-d', (string) $ends->max());
                } catch (Throwable) {
                    return;
                }

                if ($end->greaterThan($start->copy()->addYear())) {
                    $validator->errors()->add(
                        'timeline',
                        __('The timeline cannot run longer than one year from the start date to the completion date.'),
                    );
                }
            },
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
            'milestones.*.starts_on.after_or_equal' => __('Work cannot start in the past. Pick today or a later date.'),
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
