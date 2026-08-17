<?php

namespace App\Http\Requests\Client;

use App\Enums\ProjectStatus;
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
        ];
    }

    /**
     * Normalise the toggle inputs the design renders as switches.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
        ]);
    }
}
