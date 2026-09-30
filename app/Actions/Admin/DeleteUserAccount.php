<?php

namespace App\Actions\Admin;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently remove a student or client account, on an administrator's word.
 *
 * The account row goes, and with it everything the database hangs off it
 * (profiles, applications, memberships, notifications, sessions). So does the
 * team the account owns when nobody else is on it: a client's business — with
 * its postings and business profile — or a student's own team. A team other
 * people still sit on is left to them.
 *
 * Teams are soft-deleted elsewhere; here they are force-deleted, because
 * "delete this account" has to mean the data is gone, not hidden.
 */
class DeleteUserAccount
{
    public function handle(User $user): void
    {
        $avatar = $user->avatar_path;

        DB::transaction(function () use ($user): void {
            $ownedTeams = Team::withTrashed()
                ->whereIn('id', $user->teamMemberships()
                    ->where('role', TeamRole::Owner->value)
                    ->select('team_id'))
                ->get();

            foreach ($ownedTeams as $team) {
                $othersOnIt = $team->memberships()->where('user_id', '!=', $user->id)->exists();

                if (! $othersOnIt) {
                    $team->forceDelete();
                }
            }

            $user->delete();
        });

        if ($avatar !== null) {
            Storage::disk('public')->delete($avatar);
        }
    }
}
