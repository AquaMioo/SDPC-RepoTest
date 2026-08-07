<?php

namespace App\Actions\Client;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DuplicateProject
{
    /**
     * Copy a posting into a fresh draft.
     *
     * Applications, attachments and the publication stamp are deliberately
     * left behind — only the brief itself is reusable.
     */
    public function handle(Project $project, User $author): Project
    {
        return DB::transaction(function () use ($project, $author) {
            $copy = $project->replicate([
                'slug', 'status', 'published_at', 'applications_open',
                'created_at', 'updated_at', 'deleted_at',
            ]);

            $copy->title = "{$project->title} (copy)";
            $copy->slug = Project::generateUniqueSlug($copy->title);
            $copy->status = ProjectStatus::Draft;
            $copy->published_at = null;
            $copy->applications_open = true;
            $copy->created_by = $author->id;
            $copy->save();

            $copy->skills()->sync(
                $project->skills->mapWithKeys(fn ($skill) => [
                    $skill->id => ['is_required' => $skill->pivot->is_required],
                ])->all(),
            );

            foreach ($project->milestones as $milestone) {
                $copy->milestones()->create([
                    'title' => $milestone->title,
                    'due_date' => $milestone->due_date,
                    'amount' => $milestone->amount,
                    'position' => $milestone->position,
                ]);
            }

            return $copy->refresh();
        });
    }
}
