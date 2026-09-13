<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\VoteToRemoveMember;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $team);

        $newRole = TeamRole::from($request->validated('role'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->update(['role' => $newRole]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Vote to remove the specified team member.
     *
     * Removal is the group's call, not one person's: every member except the
     * one being removed has to agree. This endpoint records the caller's vote
     * and takes the member out on the last one — so in a team of two, where
     * "everyone except the target" is a single person, it still behaves like
     * the direct removal it used to be.
     *
     * Any member may vote, which is why there is no `removeMember` gate here.
     * That permission asks "may you remove somebody", and now nobody can on
     * their own. What guards this instead is membership: a stranger cannot
     * vote, and the owner cannot be voted out at all.
     */
    public function destroy(Request $request, Team $team, User $user, VoteToRemoveMember $vote): RedirectResponse
    {
        $voter = $request->user();

        abort_unless($voter->belongsToTeam($team), 403);
        abort_unless($user->belongsToTeam($team), 404);

        /*
         * A team left with nobody able to rename it, invite to it or answer
         * for it is worse than one holding a member somebody wanted out. The
         * owner leaves by handing the team over or deleting it.
         */
        abort_if($team->owner()?->is($user), 403, __('The team owner cannot be removed.'));

        $result = $vote->handle($team, $user, $voter);

        Inertia::flash('toast', $result['removed']
            ? ['type' => 'success', 'message' => __('Member removed.')]
            : [
                'type' => 'success',
                'message' => __('Your vote is in — :votes of :needed. :name leaves the team once everybody else agrees.', [
                    'votes' => $result['votes'],
                    'needed' => $result['needed'],
                    'name' => $user->name,
                ]),
            ]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
