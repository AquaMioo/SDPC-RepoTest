<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use App\Models\AgreementMilestone;
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
     * Keep Turnover to itself, and its end where the client agreed it.
     *
     * Design and Build may overlap — a build often starts before every screen
     * is drawn. Turnover may not: it begins after every other phase ends. Its
     * end is the final deadline, which ends the project, so it only moves
     * through a change the client approves (DeadlineChangeRequestController).
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

                $phases = $agreement->milestones()->get();
                $turnover = $phases->sortBy('position')->last();

                if ($turnover === null || $phases->count() < 2) {
                    return;
                }

                $startsOn = Carbon::parse($this->input('starts_on'));
                $endsOn = Carbon::parse($this->input('ends_on'));

                if ($turnover->is($milestone)) {
                    $finalDeadline = $turnover->scheduledEndsOn();

                    if ($finalDeadline !== null && ! $endsOn->isSameDay($finalDeadline)) {
                        $validator->errors()->add('ends_on', __("The final deadline only moves with the client's approval. Ask for a change instead."));
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
