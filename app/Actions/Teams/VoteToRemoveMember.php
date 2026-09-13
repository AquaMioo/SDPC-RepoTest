<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Record one member's agreement to remove another, and act on it once the
 * whole team agrees.
 *
 * Removal is not one person's decision. A team is four people at most and they
 * are seeing a project through together, so somebody being put out of it is
 * the group's call — every member except the one being removed has to say yes.
 *
 * That threshold is what keeps small teams working the way they always did: in
 * a team of two, "everyone except the target" is one person, so the remaining
 * member decides alone and nothing feels ceremonial. It only becomes a vote
 * when there is actually a group to disagree.
 *
 * The owner is exempt from being removed — a team with nobody able to rename
 * it, invite to it or answer for it is worse than one with a member somebody
 * wanted out. The owner leaves by handing the team over or deleting it.
 */
class VoteToRemoveMember
{
    /**
     * @return array{removed: bool, votes: int, needed: int}
     */
    public function handle(Team $team, User $target, User $voter): array
    {
        return DB::transaction(function () use ($team, $target, $voter): array {
            $team->removalVotes()->firstOrCreate([
                'target_user_id' => $target->id,
                'voter_id' => $voter->id,
            ]);

            /*
             * Counted from the members who are actually still here rather than
             * from every vote ever cast: somebody who has since left the team
             * should not carry a removal they can no longer be part of.
             */
            $electorate = $team->members()
                ->where('users.id', '!=', $target->id)
                ->pluck('users.id');

            $votes = $team->removalVotes()
                ->where('target_user_id', $target->id)
                ->whereIn('voter_id', $electorate)
                ->count();

            $needed = $electorate->count();

            if ($votes < $needed) {
                return ['removed' => false, 'votes' => $votes, 'needed' => $needed];
            }

            $this->remove($team, $target);

            return ['removed' => true, 'votes' => $votes, 'needed' => $needed];
        });
    }

    /**
     * Take one member out, and clear every vote that named them.
     *
     * Both directions: the votes to remove them, and the votes they cast about
     * somebody else. Leaving either behind would let a decision made by a
     * person who is no longer on the team settle a later one.
     */
    protected function remove(Team $team, User $target): void
    {
        $team->memberships()->where('user_id', $target->id)->delete();

        /*
         * Grouped. Left ungrouped, the orWhere escapes the relation's own
         * team_id constraint by operator precedence — `team_id = X AND target
         * = Y OR voter = Y` — and takes this person's votes out of every other
         * team they belong to as well.
         */
        $team->removalVotes()
            ->where(fn ($votes) => $votes
                ->where('target_user_id', $target->id)
                ->orWhere('voter_id', $target->id))
            ->delete();

        if ($target->isCurrentTeam($team)) {
            $target->switchTeam($target->personalTeam());
        }
    }
}
