<?php

namespace App\Http\Requests\Admin;

use App\Enums\IssueResolution;
use App\Models\Issue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ResolveIssueRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(IssueResolution::values())],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $action = IssueResolution::tryFrom((string) $this->input('action'));
            $issue = $this->route('issue');

            // Closing a posting on a report that names no posting would close
            // nothing and still read as a decision on the queue.
            if ($action?->needsPosting() && $issue instanceof Issue && ! $issue->isAboutPosting()) {
                $validator->errors()->add(
                    'action',
                    __('This report is not about a posting.'),
                );
            }
        });
    }

    /**
     * Get the validated action as an enum.
     */
    public function action(): IssueResolution
    {
        return IssueResolution::from((string) $this->validated('action'));
    }
}
