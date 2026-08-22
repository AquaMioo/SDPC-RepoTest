<?php

namespace App\Http\Requests;

use App\Enums\IssueCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A report about an account, or about one of its postings.
 *
 * Both shapes come through here. `reported_project_id` is what separates them:
 * when it is present the account being reported is the one behind the posting
 * and is resolved server side, because a browser naming both independently
 * could pin a posting on somebody who never wrote it.
 */
class ReportAccountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reported_project_id' => ['nullable', 'integer', Rule::exists(Project::class, 'id')],
            'reported_user_id' => [
                Rule::requiredIf(fn (): bool => ! $this->isAboutPosting()),
                'nullable',
                'integer',
                Rule::exists(User::class, 'id'),
            ],
            'category' => ['required', 'string', Rule::enum(IssueCategory::class)],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }

    /**
     * Determine if this report names a posting.
     */
    public function isAboutPosting(): bool
    {
        return $this->filled('reported_project_id');
    }

    /**
     * Get the posting being reported, if there is one.
     */
    public function reportedProject(): ?Project
    {
        if (! $this->isAboutPosting()) {
            return null;
        }

        return Project::query()
            ->with('team')
            ->find((int) $this->input('reported_project_id'));
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectSelfReport($validator);
            $this->rejectCategoryThatDoesNotFitAPosting($validator);
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.min' => 'Please describe what happened in at least 20 characters, so an administrator can act on it.',
        ];
    }

    /**
     * Refuse a report someone has filed against themselves.
     *
     * For a posting that means the team it belongs to, not just the account
     * that typed it: a colleague reporting their own team's work is the same
     * complaint wearing a different name.
     */
    private function rejectSelfReport(Validator $validator): void
    {
        $project = $this->reportedProject();

        if ($project !== null) {
            if ($this->user()->belongsToTeam($project->team)) {
                $validator->errors()->add(
                    'reported_project_id',
                    __('You cannot report your own posting.'),
                );
            }

            return;
        }

        if ((int) $this->input('reported_user_id') === $this->user()->id) {
            $validator->errors()->add(
                'reported_user_id',
                __('You cannot report your own account.'),
            );
        }
    }

    /**
     * Refuse a category that says nothing about a posting.
     */
    private function rejectCategoryThatDoesNotFitAPosting(Validator $validator): void
    {
        if (! $this->isAboutPosting()) {
            return;
        }

        $category = IssueCategory::tryFrom((string) $this->input('category'));

        if ($category !== null && ! $category->appliesToPosting()) {
            $validator->errors()->add(
                'category',
                __('That reason describes an account rather than a posting.'),
            );
        }
    }
}
