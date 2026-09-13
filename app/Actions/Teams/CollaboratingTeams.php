<?php

namespace App\Actions\Teams;

use App\Enums\AgreementStatus;
use App\Models\Agreement;
use App\Models\Team;
use App\Models\User;

/**
 * The student teams a client is actually working with.
 *
 * A client never raises a team of their own beyond the business they register
 * as, so their Teams screen would otherwise be a single row about themselves.
 * What they want from it is the other side: the group building their project.
 *
 * The gate is a signed agreement, deliberately, and not an application or an
 * invitation. Team membership says who somebody works with, which is theirs to
 * disclose — a client who has merely been applied to has no claim on it. Once
 * both parties have signed, the working relationship exists and the client is
 * entitled to know who is on the other side of it.
 */
class CollaboratingTeams
{
    /**
     * List the teams of every student under contract with this client.
     *
     * @return array<int, array{id: int, name: string, slug: string, student: string, members: array<int, string>}>
     */
    public function handle(User $client): array
    {
        $students = Agreement::query()
            ->whereIn('team_id', $client->teams()->select('teams.id'))
            ->where('status', AgreementStatus::Active)
            ->with('student.currentTeam.members')
            ->get()
            ->pluck('student')
            ->filter();

        return $students
            /*
             * One row per team, not per agreement. A client running two builds
             * with the same group would otherwise see them twice, and two
             * students from one team would collapse into whichever came first
             * — so the team id is the key and the students are gathered onto
             * it rather than the other way round.
             */
            ->groupBy(fn (User $student): ?int => $student->currentTeam?->id)
            ->reject(fn ($group, $teamId): bool => $teamId === null)
            ->map(function ($group): array {
                /** @var Team $team */
                $team = $group->first()->currentTeam;

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'student' => $group->pluck('name')->unique()->join(', '),
                    'members' => $team->members->pluck('name')->all(),
                ];
            })
            ->values()
            ->all();
    }
}
