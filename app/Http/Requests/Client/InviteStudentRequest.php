<?php

namespace App\Http\Requests\Client;

use App\Enums\TeamPermission;
use App\Enums\UserRole;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
