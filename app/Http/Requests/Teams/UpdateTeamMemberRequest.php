<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Rules\UnclaimedTeamRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');
        $member = $this->route('user');

        abort_if(! $team instanceof Team, 404);

        return [
            'role' => [
                'required',
                'string',
                Rule::in(array_column(TeamRole::assignable(), 'value')),
                // One holder per job title; the member's own role is theirs to keep.
                new UnclaimedTeamRole($team, $member instanceof User ? $member->id : null),
            ],
        ];
    }
}
