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
            $this->syncMilestones($project, $attributes['milestones'] ?? []);

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
            $this->syncMilestones($project, $attributes['milestones'] ?? []);

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
     * Map validated input onto the project's own columns.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function projectAttributes(array $attributes, ?Project $project = null): array
    {
        $status = ProjectStatus::from($attributes['status']);

        return [
            'title' => $attributes['title'],
            'description' => $attributes['description'],
            'objectives' => $attributes['objectives'] ?? null,
            'category' => $attributes['category'],
            'industry' => $attributes['industry'] ?? null,
            'team_size' => $attributes['team_size'],
            'experience_level' => $attributes['experience_level'],
            'open_to_capstone_groups' => $attributes['open_to_capstone_groups'] ?? false,
            'budget_type' => $attributes['budget_type'],
            'budget_amount' => $attributes['budget_amount'] ?? null,
            'hide_budget' => $attributes['hide_budget'] ?? false,
            'start_date' => $attributes['start_date'] ?? null,
            'target_delivery_date' => $attributes['target_delivery_date'] ?? null,
            'application_deadline' => $attributes['application_deadline'] ?? null,
            'expected_completion_date' => $attributes['expected_completion_date'] ?? null,
            'weekly_commitment' => $attributes['weekly_commitment'] ?? null,
            'visibility' => $attributes['visibility'],
            'preferred_school_id' => $attributes['preferred_school_id'] ?? null,
            'preferred_course_id' => $attributes['preferred_course_id'] ?? null,
            'preferred_year_level' => $attributes['preferred_year_level'] ?? null,
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

    /**
     * Replace the project's milestones with the submitted set.
     *
     * @param  array<int, array<string, mixed>>  $milestones
     */
    protected function syncMilestones(Project $project, array $milestones): void
    {
        $project->milestones()->delete();

        foreach (array_values($milestones) as $position => $milestone) {
            $project->milestones()->create([
                'title' => $milestone['title'],
                'due_date' => $milestone['due_date'] ?? null,
                'amount' => $milestone['amount'] ?? null,
                'position' => $position,
            ]);
        }
    }
}
