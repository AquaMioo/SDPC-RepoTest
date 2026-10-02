<?php

namespace App\Http\Requests\Agreements;

use App\Enums\MemorandumSection;
use App\Models\Agreement;
use App\Models\AgreementRequirement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One line added to an optional section of the memorandum (IV, V, VI, VIII
 * or IX), or a change to one the user added. Section VII has its own request,
 * since each service is an Objective with a Scope.
 */
class SaveMemorandumRequirementRequest extends FormRequest
{
    /** How many lines one section takes, so the printed copy stays a contract. */
    public const MAX_PER_SECTION = 20;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Adding is open to both parties until somebody signs; changing is only
     * for the person who added the line (AgreementPolicy).
     */
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');
        $requirement = $this->route('requirement');

        if (! $agreement instanceof Agreement) {
            return false;
        }

        return $requirement instanceof AgreementRequirement
            ? Gate::allows('changeRequirement', [$agreement, $requirement])
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
            /* Only when adding: a line stays in the section it was added to. */
            'section' => [
                Rule::requiredIf(! $this->route('requirement') instanceof AgreementRequirement),
                Rule::in(array_map(fn (MemorandumSection $section): string => $section->value, MemorandumSection::requirementSections())),
            ],
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Keep each section to a printable length.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $agreement = $this->route('agreement');

                if ($validator->errors()->isNotEmpty()
                    || $this->route('requirement') instanceof AgreementRequirement
                    || ! $agreement instanceof Agreement) {
                    return;
                }

                $count = $agreement->requirements()->where('section', $this->input('section'))->count();

                if ($count >= self::MAX_PER_SECTION) {
                    $validator->errors()->add('body', __('A section takes at most :max added lines.', ['max' => self::MAX_PER_SECTION]));
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
            'body.required' => __('Write the requirement before saving it.'),
        ];
    }
}
