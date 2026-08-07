<?php

namespace App\Http\Requests\Client;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RespondToApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->route('application');

        if (! $application instanceof Application) {
            return false;
        }

        return Gate::allows($this->abilityForStatus(), $application);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(
                collect(ApplicationStatus::clientAssignable())
                    ->map(fn (ApplicationStatus $status) => $status->value)
                    ->all(),
            )],
        ];
    }

    /**
     * Get the resolved status the client is moving the application into.
     */
    public function status(): ApplicationStatus
    {
        return ApplicationStatus::from($this->string('status')->toString());
    }

    /**
     * Map the requested status onto the policy ability that guards it.
     */
    protected function abilityForStatus(): string
    {
        return match ($this->input('status')) {
            ApplicationStatus::Shortlisted->value => 'shortlist',
            ApplicationStatus::Accepted->value => 'accept',
            ApplicationStatus::Rejected->value => 'reject',
            default => 'view',
        };
    }
}
