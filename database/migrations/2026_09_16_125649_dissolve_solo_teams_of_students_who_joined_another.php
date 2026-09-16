<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bring existing accounts in line with "one team per student".
 *
 * Before JoinTeam, accepting an invitation left the student's own sign-up team
 * in place, so a student could be on two teams at once. From now on joining
 * dissolves the student's own team when nobody else is on it; this applies the
 * same rule, once, to the accounts that joined before it existed.
 *
 * Deliberately narrow. Only a student who is a member (not owner) of exactly
 * one other team is touched, and only their own teams that nobody else is on
 * are dissolved. Anything more tangled — a student leading a group while also
 * sitting on somebody else's, or on two other teams — is left alone and logged,
 * because there is no safe guess about which team they meant to keep.
 *
 * Teams are soft-deleted, the same as TeamController::destroy, so a mistake is
 * recoverable from the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        $students = DB::table('users')->where('role', 'student')->pluck('current_team_id', 'id');

        foreach ($students as $userId => $currentTeamId) {
            $memberships = DB::table('team_members')
                ->join('teams', 'teams.id', '=', 'team_members.team_id')
                ->whereNull('teams.deleted_at')
                ->where('team_members.user_id', $userId)
                ->get(['team_members.team_id', 'team_members.role']);

            $joined = $memberships->where('role', '!=', 'owner');
            $owned = $memberships->where('role', 'owner');

            if ($joined->isEmpty() || $owned->isEmpty()) {
                continue;
            }

            $soloOwned = $owned->filter(fn (object $membership): bool => DB::table('team_members')
                ->where('team_id', $membership->team_id)
                ->count() === 1);

            if ($joined->count() > 1 || $soloOwned->count() !== $owned->count()) {
                Log::warning('A student is on more than one team and was left for a person to sort out.', [
                    'user_id' => $userId,
                    'team_ids' => $memberships->pluck('team_id')->all(),
                ]);

                continue;
            }

            $keptTeamId = $joined->first()->team_id;

            DB::transaction(function () use ($soloOwned, $userId, $currentTeamId, $keptTeamId): void {
                foreach ($soloOwned->pluck('team_id') as $teamId) {
                    DB::table('conversations')->where('student_team_id', $teamId)->update(['student_team_id' => null]);
                    DB::table('team_invitations')->where('team_id', $teamId)->delete();
                    DB::table('team_removal_votes')->where('team_id', $teamId)->delete();
                    DB::table('team_members')->where('team_id', $teamId)->delete();
                    DB::table('teams')->where('id', $teamId)->update(['deleted_at' => now()]);
                }

                if ($soloOwned->pluck('team_id')->contains($currentTeamId) || $currentTeamId === null) {
                    DB::table('users')->where('id', $userId)->update(['current_team_id' => $keptTeamId]);
                }
            });
        }
    }

    /**
     * Nothing to put back automatically: the dissolved teams are soft-deleted
     * and can be restored by hand if a student needs theirs again.
     */
    public function down(): void
    {
        //
    }
};
