<?php

namespace App\Http\Requests\Agreements;

use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use App\Enums\MilestoneStatus;
use App\Models\AgreementMilestone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMilestoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The two sides move a milestone in opposite directions and neither can do
     * the other's half: a student says "started" and "handed over", a client
     * says "approved" and "sent back". Which statuses are offered is decided
     * here rather than in the controller so an unauthorised transition is a
     * validation failure with a reason, not a bare 403.
     */
    public function authorize(): bool
    {
        $milestone = $this->route('milestone');

        if (! $milestone instanceof AgreementMilestone) {
            return false;
        }

        return $this->party() !== null
            && $milestone->agreement->status === AgreementStatus::Active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assignable = $this->party() === AgreementParty::Student
            ? MilestoneStatus::studentAssignable()
            : MilestoneStatus::clientAssignable();

        return [
            'status' => ['required', Rule::in(
                collect($assignable)->map(fn (MilestoneStatus $status) => $status->value)->all(),
            )],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get the resolved status the milestone is moving into.
     */
    public function status(): MilestoneStatus
    {
        return MilestoneStatus::from($this->string('status')->toString());
    }

    /**
     * Get the side of the agreement the signed-in user acts for.
     */
    public function party(): ?AgreementParty
    {
        $milestone = $this->route('milestone');
        $user = $this->user();

        if (! $milestone instanceof AgreementMilestone || $user === null) {
            return null;
        }

        $agreement = $milestone->agreement;

        if ($user->id === $agreement->student_id) {
            return AgreementParty::Student;
        }

        return $user->belongsToTeam($agreement->team) ? AgreementParty::Client : null;
    }
}
