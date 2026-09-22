<?php

namespace App\Rules;

use App\Enums\TeamRole;
use App\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * One person per job title: a team has a single Project Manager, a single
 * Quality Assurance, and so on.
 *
 * Both doors are guarded, because both hand a role out: inviting somebody with
 * a role, and changing the role a member already holds. Invitations that are
 * still waiting count as taken — the seat is promised and its role with it —
 * which is why this reads Team::takenRoles() rather than the members alone.
 */
class UnclaimedTeamRole implements ValidationRule
{
    /**
     * @param  int|null  $exceptUserId  The member being re-assigned, whose own role is not a clash.
     */
    public function __construct(
        protected Team $team,
        protected ?int $exceptUserId = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $role = TeamRole::tryFrom((string) $value);

        if ($role === null || ! in_array($role, TeamRole::assignableCases(), true)) {
            return;
        }

        if (in_array($role->value, $this->team->takenRoles($this->exceptUserId), true)) {
            $fail(__('This team already has a :role. Each of the four roles is held by one person.', [
                'role' => $role->label(),
            ]));
        }
    }
}
