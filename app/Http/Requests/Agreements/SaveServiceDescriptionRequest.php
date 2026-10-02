<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use App\Models\AgreementMilestone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * One Section VII service: its Objective (a title) and its Scope (what it
 * covers). Each becomes a phase of the project, before Turnover, which is how
 * the Objective and Scope reach Project Management.
 */
class SaveServiceDescriptionRequest extends FormRequest
{
    /**
     * How many services Section VII takes: the schedule holds 12 phases, and
     * Turnover is always one of them.
     */
    public const MAX_SERVICES = 11;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Either party may add a service until somebody signs; only the person
     * who added one may change it (AgreementPolicy).
     */
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');
        $service = $this->route('service');

        if (! $agreement instanceof Agreement) {
            return false;
        }

        return $service instanceof AgreementMilestone
            ? Gate::allows('changeRequirement', [$agreement, $service])
            : Gate::allows('addRequirements', $agreement);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'objective' => ['required', 'string', 'max:120'],
            'scope' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Keep Section VII within the schedule's twelve phases.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $agreement = $this->route('agreement');

                if ($validator->errors()->isNotEmpty()
                    || $this->route('service') instanceof AgreementMilestone
                    || ! $agreement instanceof Agreement) {
                    return;
                }

                if ($agreement->servicePhases()->count() >= self::MAX_SERVICES) {
                    $validator->errors()->add('objective', __('Section VII takes at most :max services.', ['max' => self::MAX_SERVICES]));
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
            'objective.required' => __('Give the service its objective (a title).'),
            'scope.required' => __('Describe the scope of the service.'),
        ];
    }
}
