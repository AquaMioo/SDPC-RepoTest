<?php

namespace App\Http\Requests\Agreements;

use App\Enums\MemorandumSection;
use App\Models\Agreement;
use App\Models\AgreementRequirement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * One Section VII service: its Objective (a title) and its Scope (what it
 * covers). When the agreement starts, the objective becomes a task in the
 * Objective phase and the scope a task in the Scope phase (SeedServiceTasks).
 */
class SaveServiceDescriptionRequest extends FormRequest
{
    /** How many services Section VII takes, so the printed copy stays a contract. */
    public const MAX_SERVICES = 20;

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

        if ($service instanceof AgreementRequirement) {
            return $service->section === MemorandumSection::Services
                && Gate::allows('changeRequirement', [$agreement, $service]);
        }

        return Gate::allows('addRequirements', $agreement);
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
     * Keep Section VII to a printable length.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $agreement = $this->route('agreement');

                if ($validator->errors()->isNotEmpty()
                    || $this->route('service') instanceof AgreementRequirement
                    || ! $agreement instanceof Agreement) {
                    return;
                }

                if ($agreement->services()->count() >= self::MAX_SERVICES) {
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
