<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;

/**
 * Make sure a student who has left, or been removed from, a team still has one.
 *
 * Joining a team replaces the student's own (see JoinTeam), so stepping out of
 * the joined team would otherwise leave the account with no team at all — and
 * every screen on the platform is mounted on the current team. They get a
 * fresh team of their own, the same kind sign up gives them.
 */
class GiveOwnTeam
{
    public function __construct(private readonly CreateTeam $createTeam) {}

    /**
     * Point the user at a team they are on, creating one if they have none.
     */
    public function handle(User $user): Team
    {
        $team = $user->fallbackTeam();

        if ($team !== null) {
            $user->switchTeam($team);

            return $team;
        }

        return $this->createTeam->handle($user, $user->name."'s Team", isPersonal: true);
    }
}
