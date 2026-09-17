<?php

namespace App\Actions\Teams;

use App\Models\Conversation;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\InviteeJoinedAnotherTeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Put a student on the team that invited them — and keep them on one team.
 *
 * A student belongs to exactly one team. Two or more is never allowed, so
 * joining is decided by what the student is on already:
 *
 *   - their own team, with nobody else in it → that team is dissolved and the
 *     team they joined becomes theirs ("You've joined the team")
 *   - their own team, with people who joined them → refused: they lead a group
 *     ("You've created the team"), and walking out on it is not a side effect
 *     of accepting an invitation
 *   - somebody else's team → refused: leave it first
 *
 * Only students join teams. A client's team is the business, and the client
 * side neither invites nor is invited — an invitation that somehow reaches a
 * client account is refused rather than seating them in a student group.
 */
class JoinTeam
{
    /**
     * Accept an invitation for the user.
     *
     * @throws ValidationException when the student already belongs to a team that cannot be replaced.
     */
    public function handle(User $user, TeamInvitation $invitation): Team
    {
        $team = $invitation->team;

        return DB::transaction(function () use ($user, $invitation, $team): Team {
            $replaced = $this->replaceableTeams($user, $team);

            $team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role],
            );

            $invitation->update(['accepted_at' => now()]);

            $user->switchTeam($team);

            foreach ($replaced as $old) {
                $this->dissolve($old);
            }

            $this->cancelOtherInvitations($user, $invitation);

            return $team;
        });
    }

    /**
     * Cancel the other team invitations waiting for this student, and tell
     * whoever sent each one.
     *
     * Joining one team means none of the others can be accepted, and their
     * senders were left waiting on an answer that could not come. A failed
     * email never undoes the join.
     */
    protected function cancelOtherInvitations(User $user, TeamInvitation $accepted): void
    {
        $others = TeamInvitation::query()
            ->pendingFor($user->email)
            ->whereKeyNot($accepted->id)
            ->with(['team', 'inviter'])
            ->get();

        foreach ($others as $other) {
            try {
                $other->inviter?->notify(new InviteeJoinedAnotherTeam($other, $user, $accepted->team));
            } catch (Throwable $exception) {
                Log::warning('A team invitation was cancelled but its sender could not be told.', [
                    'team_invitation_id' => $other->id,
                    'reason' => $exception->getMessage(),
                ]);
            }

            $other->delete();
        }
    }

    /**
     * Why the student cannot join another team, or null when they can.
     *
     * Asked before an invitation is sent as well as when it is accepted, so a
     * team lead is told up front rather than their invitee finding out later.
     *
     * @param  bool  $toThemselves  Word it for the student rather than for whoever is inviting them.
     */
    public function refusal(User $user, ?Team $joining = null, bool $toThemselves = false): ?string
    {
        if (! $user->isStudent()) {
            return __('Only students can be invited to a team.');
        }

        foreach ($user->teams()->get() as $current) {
            if ($joining !== null && $current->is($joining)) {
                continue;
            }

            if (! $user->ownsTeam($current)) {
                return $toThemselves
                    ? __('You are already on the team ":team". A student can only be on one team, so leave it first and then accept.', ['team' => $current->name])
                    : __(':name is already on the team ":team". A student can only be on one team, so they have to leave it first.', ['name' => $user->name, 'team' => $current->name]);
            }

            if (! $current->isSolo()) {
                return $toThemselves
                    ? __('You lead the team ":team", and others have joined you. A student can only be on one team, so you cannot join another while you lead yours.', ['team' => $current->name])
                    : __(':name leads the team ":team", which others have joined. A student can only be on one team, so they cannot join another while they lead theirs.', ['name' => $user->name, 'team' => $current->name]);
            }
        }

        return null;
    }

    /**
     * The student's own solo teams, which joining replaces.
     *
     * @return list<Team>
     */
    protected function replaceableTeams(User $user, Team $joining): array
    {
        $refusal = $this->refusal($user, $joining, toThemselves: true);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['invitation' => $refusal]);
        }

        return $user->teams()
            ->where('teams.id', '!=', $joining->id)
            ->get()
            ->all();
    }

    /**
     * Remove a solo team the student no longer needs.
     *
     * Everything that hangs off a team goes with it: its membership, the
     * invitations it had sent (they would otherwise invite people into a team
     * that no longer exists), and any removal votes. A thread that had this
     * team attached falls back to the student alone; opening it again adopts
     * the team they joined.
     */
    protected function dissolve(Team $team): void
    {
        $threads = Conversation::query()->where('student_team_id', $team->id);

        DB::table('conversation_members')->whereIn('conversation_id', (clone $threads)->select('id'))->delete();

        $threads->update(['student_team_id' => null]);

        $team->invitations()->delete();
        $team->removalVotes()->delete();
        $team->memberships()->delete();
        $team->delete();
    }
}
