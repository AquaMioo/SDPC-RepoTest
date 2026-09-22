<?php

namespace App\Http\Requests\Client;

use App\Enums\TeamPermission;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InviteStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');
        $user = $this->user();

        return $project instanceof Project
            && $user !== null
            && $user->belongsToTeam($project->team)
            && $user->hasTeamPermission($project->team, TeamPermission::ManageApplications);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'user_id' => [
                'required',
                'integer',
                /** Only students can be invited onto a project. */
                Rule::exists('users', 'id')->where('role', UserRole::Student->value),
                /** One link per student per project — the table enforces it too. */
                Rule::unique('applications', 'user_id')
                    ->where('project_id', $project instanceof Project ? $project->id : null),
            ],
        ];
    }

    /**
     * Refuse a student who has already been taken on elsewhere.
     *
     * A student works on one project at a time, so an invitation to somebody
     * already taken on could never be accepted. Said now, to the business
     * asking, rather than left open for them to wait on.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $student = User::query()->find($this->integer('user_id'));

            if ($student?->isLockedToProject()) {
                $validator->errors()->add(
                    'user_id',
                    __(':name has already accepted an invitation from another client, so they cannot be invited right now. They become available again once that project is finished.', ['name' => $student->name]),
                );
            }
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
            'user_id.unique' => 'That student is already linked to this project.',
            'user_id.exists' => 'That student account could not be found.',
        ];
    }
}
