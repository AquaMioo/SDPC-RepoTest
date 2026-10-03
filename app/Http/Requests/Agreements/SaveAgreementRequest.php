<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use App\Rules\WithinTimelineWindow;
use App\Support\TimelineWindow;
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
     * Milestones arrive whole rather than one at a time, so the schedule is
     * one negotiation and a half-saved one never reaches the other party.
     * What the client sets on them is the timeline: each phase's dates. The
     * phases are fixed (Objective, Scope, Turnover), so the title sent here
     * is ignored, and phases are neither added nor removed through this form.
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

            /* Every timeline date: today at the earliest, a year from today at the latest. */
            'starts_on' => ['nullable', 'date_format:Y-m-d', new WithinTimelineWindow],
            'ends_on' => ['nullable', 'date_format:Y-m-d', new WithinTimelineWindow, 'after_or_equal:starts_on'],

            'milestones' => ['required', 'array', 'min:1', 'max:12'],
            'milestones.*.id' => ['required', 'integer'],
            'milestones.*.title' => ['nullable', 'string', 'max:120'],
            'milestones.*.description' => ['nullable', 'string', 'max:2000'],
            /* Whole pesos, and a ceiling that stops a typo becoming a contract. */
            'milestones.*.amount' => ['required', 'integer', 'min:0', 'max:10000000'],
            'milestones.*.starts_on' => ['nullable', 'date_format:Y-m-d', new WithinTimelineWindow],
            'milestones.*.ends_on' => ['nullable', 'date_format:Y-m-d', new WithinTimelineWindow, 'after_or_equal:milestones.*.starts_on'],
        ];
    }

    /**
     * Give Turnover at least a month.
     *
     * The Objective and Scope phases may overlap one another however the two sides
     * like. Turnover, the last phase, has to run one calendar month or more,
     * so the hand-over is never squeezed into a few days. The year-long cap
     * on the whole timeline is the date window on every field above.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $agreement = $this->route('agreement');

                if (! $agreement instanceof Agreement) {
                    return;
                }

                $turnoverId = $agreement->loadMissing('milestones')->turnoverPhase()?->id;

                foreach ((array) $this->input('milestones', []) as $index => $milestone) {
                    if (! is_array($milestone) || (int) ($milestone['id'] ?? 0) !== $turnoverId) {
                        continue;
                    }

                    if ($validator->errors()->hasAny(["milestones.{$index}.starts_on", "milestones.{$index}.ends_on"])) {
                        return;
                    }

                    try {
                        $startsOn = Carbon::createFromFormat('!Y-m-d', (string) ($milestone['starts_on'] ?? ''));
                        $endsOn = Carbon::createFromFormat('!Y-m-d', (string) ($milestone['ends_on'] ?? ''));
                    } catch (Throwable) {
                        return;
                    }

                    if (! TimelineWindow::isLongEnoughForTurnover($startsOn, $endsOn)) {
                        $validator->errors()->add("milestones.{$index}.ends_on", TimelineWindow::turnoverTooShortMessage($startsOn));
                    }
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
            'milestones.*.ends_on.after_or_equal' => __('A phase cannot end before it starts.'),
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
