<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use App\Models\AgreementMilestone;
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
     * A new task in a Design or Build phase must carry a deadline; one in the
     * Turnover phase may, since Turnover's own end is the deadline. Either
     * way it falls on or before the final deadline. Editing never moves a
     * deadline that is already set — that needs the client (see
     * AgreementTaskController::update and DeadlineChangeRequestController).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $milestone = $this->route('milestone');

        $dueRules = ['date_format:Y-m-d'];

        if (($finalDeadline = $this->finalDeadline()) !== null) {
            $dueRules[] = 'before_or_equal:'.$finalDeadline->toDateString();
        }

        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_on' => $milestone instanceof AgreementMilestone
                ? [$milestone->isTurnover() ? 'nullable' : 'required', ...$dueRules]
                : ['sometimes', 'nullable', ...$dueRules],
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
