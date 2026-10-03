<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Support\TimelineWindow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdatePhaseScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The timeline is the student's working plan. The client reads it.
     */
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');

        return $agreement instanceof Agreement && Gate::allows('manageTasks', $agreement);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    /**
     * Keep Turnover to itself, a month long, and its end where the client
     * agreed it; keep every date that moves inside the timeline window.
     *
     * The Objective and Scope phases may overlap one
     * another. Turnover may not: it begins after every other phase ends, and
     * runs at least one month. Its end is the final deadline, which ends the
     * project, so it only moves through a change the client approves
     * (DeadlineChangeRequestController).
     *
     * A date that moves must fall between today and a year from today. One
     * that stays put is left alone, so a phase that started last week can
     * still have its end moved.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $milestone = $this->route('milestone');
                $agreement = $this->route('agreement');

                if ($validator->errors()->isNotEmpty()
                    || ! $milestone instanceof AgreementMilestone
                    || ! $agreement instanceof Agreement) {
                    return;
                }

                $startsOn = Carbon::parse($this->input('starts_on'));
                $endsOn = Carbon::parse($this->input('ends_on'));

                foreach (['starts_on' => [$startsOn, $milestone->scheduledStartsOn()], 'ends_on' => [$endsOn, $milestone->scheduledEndsOn()]] as $field => [$date, $current]) {
                    $moved = $current === null || ! $date->isSameDay($current);

                    if ($moved && ! TimelineWindow::contains($date)) {
                        $validator->errors()->add($field, $date->lt(TimelineWindow::earliest())
                            ? __('Dates cannot be in the past. Pick today or a later date.')
                            : __('Dates cannot be more than one year from today. Pick :date or earlier.', [
                                'date' => TimelineWindow::latest()->format('j M Y'),
                            ]));
                    }
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $phases = $agreement->milestones()->get();
                $turnover = $phases->sortBy('position')->last();

                if ($turnover === null) {
                    return;
                }

                if ($turnover->is($milestone)) {
                    $finalDeadline = $turnover->scheduledEndsOn();

                    if ($finalDeadline !== null && ! $endsOn->isSameDay($finalDeadline)) {
                        $validator->errors()->add('ends_on', __("The final deadline only moves with the client's approval. Ask for a change instead."));

                        return;
                    }

                    if (! TimelineWindow::isLongEnoughForTurnover($startsOn, $endsOn)) {
                        $validator->errors()->add('starts_on', __('Turnover has to run at least one month. Start it on or before :date.', [
                            'date' => TimelineWindow::latestTurnoverStart($endsOn)->format('j M Y'),
                        ]));

                        return;
                    }

                    $latestEnd = $phases
                        ->reject(fn (AgreementMilestone $phase): bool => $phase->is($turnover))
                        ->map(fn (AgreementMilestone $phase) => $phase->scheduledEndsOn())
                        ->filter()
                        ->max();

                    if ($latestEnd !== null && ! $startsOn->gt($latestEnd)) {
                        $validator->errors()->add('starts_on', __('Turnover cannot overlap the other phases. Start it after :date.', [
                            'date' => $latestEnd->format('j M Y'),
                        ]));
                    }

                    return;
                }

                $turnoverStarts = $turnover->scheduledStartsOn();

                if ($turnoverStarts !== null && ! $endsOn->lt($turnoverStarts)) {
                    $validator->errors()->add('ends_on', __('This phase has to end before Turnover starts on :date.', [
                        'date' => $turnoverStarts->format('j M Y'),
                    ]));
                }
            },
        ];
    }
}
