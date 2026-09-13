<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's agreement to remove another from their team.
 *
 * There is no proposal to go with it. The first vote is the proposal and the
 * last one carries it — see App\Actions\Teams\VoteToRemoveMember.
 *
 * @property int $id
 * @property int $team_id
 * @property int $target_user_id
 * @property int $voter_id
 */
#[Fillable(['team_id', 'target_user_id', 'voter_id'])]
class TeamRemovalVote extends Model
{
    /**
     * The team the vote is about.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The member the vote would remove.
     *
     * @return BelongsTo<User, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * The member who cast it.
     *
     * @return BelongsTo<User, $this>
     */
    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id');
    }
}
