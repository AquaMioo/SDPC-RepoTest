<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementTask;
use App\Rules\WithinTimelineWindow;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Writing the checklist is the student side's: the signer and their
     * teammates. See AgreementPolicy::manageTasks().
     */
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');

        return $agreement instanceof Agreement && Gate::allows('manageTasks', $agreement);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only a task in the Turnover phase may carry a deadline, and need not,
     * since Turnover's own end is the deadline. It falls on or before the
     * final deadline, and inside the timeline
     * window (today to a year from today). Editing never moves a
     * deadline that is already set — that needs the client (see
     * AgreementTaskController::update and DeadlineChangeRequestController).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $milestone = $this->route('milestone');

        $dueRules = ['date_format:Y-m-d'];

        /*
         * A deadline being set goes inside the timeline window: not in the
         * past, not more than a year out. Saving a task that keeps the
         * deadline it already has (even one now passed) is not setting one.
         */
        $task = $this->route('task');
        $keepsItsDeadline = $task instanceof AgreementTask
            && $task->due_on !== null
            && $this->input('due_on') === $task->due_on->toDateString();

        if (! $keepsItsDeadline) {
            $dueRules[] = new WithinTimelineWindow;
        }

        if (($finalDeadline = $this->finalDeadline()) !== null) {
            $dueRules[] = 'before_or_equal:'.$finalDeadline->toDateString();
        }

        /*
         * Only a Turnover task may carry a deadline, and need not. Objective &
         * Scope has none (owner, 2026-10-07), so a date sent for one is
         * dropped rather than stored.
         */
        $phase = $milestone instanceof AgreementMilestone
            ? $milestone
            : ($task instanceof AgreementTask ? $task->milestone : null);

        return [
            /* As long as a Section VII scope, which arrives as a task description (SeedServiceTasks). */
            'title' => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_on' => match (true) {
                $phase !== null && ! $phase->isTurnover() => ['exclude'],
                $milestone instanceof AgreementMilestone => ['nullable', ...$dueRules],
                default => ['sometimes', 'nullable', ...$dueRules],
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
            'due_on.required' => __('Give the task a deadline.'),
            'due_on.before_or_equal' => __('A task has to be due on or before the final deadline, :date.', [
                'date' => $this->finalDeadline()?->format('j M Y') ?? '',
            ]),
        ];
    }

    /**
     * The end of the Turnover phase on the agreement in the URL.
     */
    protected function finalDeadline(): ?CarbonInterface
    {
        $agreement = $this->route('agreement');

        return $agreement instanceof Agreement ? $agreement->loadMissing('milestones')->finalDeadline() : null;
    }
}
