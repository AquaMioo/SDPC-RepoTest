<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Determine whether the user can view the application.
     */
    public function view(User $user, Application $application): bool
    {
        return $user->belongsToTeam($application->project->team);
    }

    /**
     * Determine whether the user can shortlist the applicant.
     */
    public function shortlist(User $user, Application $application): bool
    {
        return $this->respond($user, $application);
    }

    /**
     * Determine whether the user can accept the applicant onto the project.
     */
    public function accept(User $user, Application $application): bool
    {
        return $this->respond($user, $application);
    }

    /**
     * Determine whether the user can reject the applicant.
     */
    public function reject(User $user, Application $application): bool
    {
        return $this->respond($user, $application);
    }

    /**
     * Determine whether the user can invite a student to a project.
     */
    public function invite(User $user, Application $application): bool
    {
        return $user->belongsToTeam($application->project->team)
            && $user->hasTeamPermission($application->project->team, TeamPermission::ManageApplications);
    }

    /**
     * Determine if the user may act on this application right now.
     *
     * Resolved and student-withdrawn applications are terminal — re-deciding
     * them would silently change a decision the student has already been told
     * about.
     */
    protected function respond(User $user, Application $application): bool
    {
        return $user->belongsToTeam($application->project->team)
            && $user->hasTeamPermission($application->project->team, TeamPermission::ManageApplications)
            && $application->status->isActionable();
    }
}
