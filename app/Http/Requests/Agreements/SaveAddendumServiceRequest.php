<?php

namespace App\Http\Requests\Agreements;

use App\Models\Addendum;
use App\Models\AddendumService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * One Section II service on the addendum: its Objective (a title) and its
 * Scope. When the down payment clears it becomes one task in Project
 * Management's Objective & Scope (SettleAddendumPayment), exactly as the
 * memorandum's Section VII services did.
 */
class SaveAddendumServiceRequest extends FormRequest
{
    /** How many services Section II takes, so the printed copy stays a contract. */
    public const MAX_SERVICES = 20;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Either party may add one until somebody signs; only its author may
     * change it (AddendumPolicy).
     */
    public function authorize(): bool
    {
        $addendum = $this->route('addendum');
        $service = $this->route('service');

        if (! $addendum instanceof Addendum) {
            return false;
        }

        if ($service instanceof AddendumService) {
            return Gate::allows('changeService', [$addendum, $service]);
        }

        return Gate::allows('edit', $addendum);
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
     * Keep Section II to a printable length.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $addendum = $this->route('addendum');

                if ($validator->errors()->isNotEmpty()
                    || $this->route('service') instanceof AddendumService
                    || ! $addendum instanceof Addendum) {
                    return;
                }

                if ($addendum->services()->count() >= self::MAX_SERVICES) {
                    $validator->errors()->add('objective', __('Section II takes at most :max services.', ['max' => self::MAX_SERVICES]));
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
