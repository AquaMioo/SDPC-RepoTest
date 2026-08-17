<?php

namespace App\Actions\Client;

use App\Enums\ProjectStatus;
use App\Enums\SkillType;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Client\ProjectPublished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SaveProject
{
    /**
     * Create a posting for the given team.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(Team $team, User $author, array $attributes): Project
    {
        return DB::transaction(function () use ($team, $author, $attributes) {
            $project = Project::create([
                ...$this->projectAttributes($attributes),
                'team_id' => $team->id,
                'created_by' => $author->id,
            ]);

            $this->syncSkills($project, $attributes['skills'] ?? []);

            $project->refresh();

            if ($project->status !== ProjectStatus::Draft) {
                $this->notifyTeam($project);
            }

            return $project;
        });
    }

    /**
     * Update an existing posting.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Project $project, array $attributes): Project
    {
        return DB::transaction(function () use ($project, $attributes) {
            $wasDraft = $project->status === ProjectStatus::Draft;

            $project->update($this->projectAttributes($attributes, $project));

            $this->syncSkills($project, $attributes['skills'] ?? []);

            $project->refresh();

            /** Only announce the first time a draft leaves the drawer. */
            if ($wasDraft && $project->status !== ProjectStatus::Draft) {
                $this->notifyTeam($project);
            }

            return $project;
        });
    }

    /**
     * Tell the owning team their posting is awaiting screening.
     */
    protected function notifyTeam(Project $project): void
    {
        Notification::send($project->team->members, new ProjectPublished($project));
    }

    /**
     * Apply the posting-review setting to a submitted status.
     *
     * Submitting for review is the only status this touches. A draft stays a
     * draft either way, and nothing here can move a posting that is already
     * open, in progress or finished.
     */
    protected function resolveStatus(ProjectStatus $status): ProjectStatus
    {
        if ($status !== ProjectStatus::PendingReview) {
            return $status;
        }

        return config('projects.auto_approve')
            ? ProjectStatus::Open
            : $status;
    }

    /**
     * Map validated input onto the project's own columns.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function projectAttributes(array $attributes, ?Project $project = null): array
    {
        $status = $this->resolveStatus(ProjectStatus::from($attributes['status']));

        return [
            'title' => $attributes['title'],
            'description' => $attributes['description'],
            'objectives' => $attributes['objectives'] ?? null,
            'category' => $attributes['category'],
            'industry' => $attributes['industry'] ?? null,
            'status' => $status,
            /**
             * Stamped the first time a posting leaves draft, and never reset,
             * so the history panel keeps the original submission date.
             */
            'published_at' => $status === ProjectStatus::Draft
                ? $project?->published_at
                : ($project?->published_at ?? now()),
        ];
    }

    /**
     * Resolve the submitted skill names and sync them onto the project.
     *
     * @param  array<int, string>  $names
     */
    protected function syncSkills(Project $project, array $names): void
    {
        $ids = collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name) => Skill::findOrCreateByName($name, SkillType::General)->id)
            ->all();

        $project->skills()->sync($ids);
    }
}
