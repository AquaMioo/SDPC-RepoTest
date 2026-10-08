<?php

namespace App\Support;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may be matched with whom, by experience (owner, 2026-10-09).
 *
 * A client with a finished project may hire new and experienced students; a
 * new client only new students. An experienced student may apply only to
 * clients with a finished project; a new student to anyone. All four rows of
 * that matrix come down to one rule: an experienced student and a new client
 * are never matched.
 *
 * A student is experienced once completed_projects_count is at least one
 * (CompleteProject counts the signer and the teammates); a client once the
 * business has a Completed project. Every door checks this on the server:
 * the Recruit list and profile, invitations and their acceptance, the board,
 * applying, and accepting an application.
 */
class HiringRule
{
    /**
     * Determine if the student has finished a project.
     */
    public static function studentIsExperienced(User $student): bool
    {
        return (int) $student->studentProfile?->completed_projects_count > 0;
    }

    /**
     * Determine if the business has finished a project.
     */
    public static function clientIsExperienced(Team $client): bool
    {
        return $client->projects()->where('status', ProjectStatus::Completed)->exists();
    }

    /**
     * Determine if this student and this business may work together.
     */
    public static function allows(User $student, Team $client): bool
    {
        return ! (static::studentIsExperienced($student) && ! static::clientIsExperienced($client));
    }

    /**
     * Narrow a student list to the ones this business may hire.
     *
     * @param  Builder<StudentProfile>  $profiles
     * @return Builder<StudentProfile>
     */
    public static function hireableBy(Builder $profiles, Team $client): Builder
    {
        return static::clientIsExperienced($client)
            ? $profiles
            : $profiles->where('student_profiles.completed_projects_count', 0);
    }

    /**
     * Narrow a posting list to the ones this student may apply to.
     *
     * @param  Builder<Project>  $projects
     * @return Builder<Project>
     */
    public static function openTo(Builder $projects, User $student): Builder
    {
        return static::studentIsExperienced($student)
            ? $projects->whereHas('team.projects', fn (Builder $finished) => $finished->where('status', ProjectStatus::Completed))
            : $projects;
    }

    /**
     * The refusal shown when a pairing breaks the rule.
     */
    public static function refusal(): string
    {
        return __('A student who has finished a project can only work with a client who has finished one too.');
    }
}
