<?php

namespace App\Http\Requests\Client;

use App\Enums\BudgetType;
use App\Enums\ExperienceLevel;
use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Shared by the create and edit posting forms.
 */
class SaveProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            ? Gate::allows('update', $project)
            : Gate::allows('create', Project::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'objectives' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],

            'skills' => ['array', 'max:20'],
            'skills.*' => ['string', 'max:60'],

            'team_size' => ['required', 'integer', 'min:1', 'max:10'],
            'experience_level' => ['required', Rule::enum(ExperienceLevel::class)],
            'open_to_capstone_groups' => ['boolean'],

            'budget_type' => ['required', Rule::enum(BudgetType::class)],
            'budget_amount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'hide_budget' => ['boolean'],

            'start_date' => ['nullable', 'date'],
            'target_delivery_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'application_deadline' => ['nullable', 'date', 'before_or_equal:target_delivery_date'],
            'expected_completion_date' => ['nullable', 'date', 'after_or_equal:target_delivery_date'],
            'weekly_commitment' => ['nullable', 'string', 'max:255'],

            'milestones' => ['array', 'max:12'],
            'milestones.*.title' => ['required', 'string', 'max:255'],
            'milestones.*.due_date' => ['nullable', 'date'],
            'milestones.*.amount' => ['nullable', 'integer', 'min:0', 'max:100000000'],

            'visibility' => ['required', Rule::enum(ProjectVisibility::class)],
            'preferred_school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'preferred_course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'preferred_year_level' => ['nullable', 'integer', 'min:1', 'max:6'],

            /** Only draft or published are client-selectable; review is admin-driven. */
            'status' => ['required', Rule::in([
                ProjectStatus::Draft->value,
                ProjectStatus::PendingReview->value,
            ])],
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
            'application_deadline.before_or_equal' => 'The application deadline must fall on or before the target delivery date.',
            'target_delivery_date.after_or_equal' => 'The target delivery date must fall on or after the preferred start date.',
        ];
    }

    /**
     * Normalise the toggle inputs the design renders as switches.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'hide_budget' => $this->boolean('hide_budget'),
            'open_to_capstone_groups' => $this->boolean('open_to_capstone_groups'),
        ]);
    }
}
