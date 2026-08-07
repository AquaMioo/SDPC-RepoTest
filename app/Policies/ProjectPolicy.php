<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can list the team's projects.
     *
     * Every member of the owning team can see the board; managing it is
     * gated separately below.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team);
    }

    /**
     * Determine whether the user can post a new project.
     */
    public function create(User $user): bool
    {
        return $user->currentTeam !== null
            && $user->hasTeamPermission($user->currentTeam, TeamPermission::ManageProjects);
    }

    /**
     * Determine whether the user can update the project.
     *
     * Completed and archived postings are frozen: their terms are what the
     * students agreed to, so they stay readable but not editable.
     */
    public function update(User $user, Project $project): bool
    {
        return $this->manages($user, $project) && $project->status->isEditable();
    }

    /**
     * Determine whether the user can archive the project.
     */
    public function archive(User $user, Project $project): bool
    {
        return $this->manages($user, $project);
    }

    /**
     * Determine whether the user can duplicate the project into a new draft.
     */
    public function duplicate(User $user, Project $project): bool
    {
        return $this->manages($user, $project) && $this->create($user);
    }

    /**
     * Determine whether the user can open or close applications.
     */
    public function toggleApplications(User $user, Project $project): bool
    {
        return $this->manages($user, $project) && $project->status->isEditable();
    }

    /**
     * Determine whether the user can review the project's applicants.
     */
    public function viewApplicants(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team)
            && $user->hasTeamPermission($project->team, TeamPermission::ManageApplications);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->manages($user, $project);
    }

    /**
     * Determine whether the user can restore a soft-deleted project.
     */
    public function restore(User $user, Project $project): bool
    {
        return $this->manages($user, $project);
    }

    /**
     * Determine if the user may manage this project at all.
     */
    protected function manages(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team)
            && $user->hasTeamPermission($project->team, TeamPermission::ManageProjects);
    }
}
