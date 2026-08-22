<?php

namespace App\Support;

use App\Enums\ProjectStatus;
use App\Models\Project;

/**
 * The postings review queue, as rows for the admin screen.
 *
 * A client publishes into `pending_review`, not straight onto the board, and
 * the student board only lists `open`. This queue is the step between the two,
 * so anything still waiting sorts to the top.
 *
 * It lives beside AdminStatistics rather than in a controller because the
 * dashboard overview draws it: the standalone postings screen is gone, and the
 * decision endpoint (AdminPostingController::update) is all that remains of it.
 */
class AdminPostingQueue
{
    /**
     * Get every posting that has been submitted, waiting ones first.
     *
     * @return array<int, array{
     *     slug: string,
     *     title: string,
     *     description: string,
     *     category: string,
     *     business: string,
     *     city: string|null,
     *     skills: array<int, string>,
     *     status: string,
     *     statusLabel: string,
     *     publishedAt: string|null,
     *     awaitingDecision: bool
     * }>
     */
    public function all(): array
    {
        return Project::query()
            ->with(['team.clientProfile', 'skills'])
            ->whereNot('status', ProjectStatus::Draft)
            ->latest('published_at')
            ->get()
            ->sortBy(fn (Project $project): int => $project->status === ProjectStatus::PendingReview ? 0 : 1)
            ->values()
            ->map(fn (Project $project): array => [
                'slug' => $project->slug,
                'title' => $project->title,
                'description' => str($project->description)->limit(240)->toString(),
                'category' => $project->category,
                'business' => $project->team->clientProfile?->business_name ?? $project->team->name,
                'city' => $project->team->clientProfile?->city,
                'skills' => $project->skills->pluck('name')->all(),
                'status' => $project->status->value,
                'statusLabel' => $project->status->label(),
                'publishedAt' => $project->published_at?->diffForHumans(),
                'awaitingDecision' => $project->status === ProjectStatus::PendingReview,
            ])
            ->all();
    }

    /**
     * Count the postings still waiting on a decision.
     */
    public function awaitingDecision(): int
    {
        return Project::query()
            ->where('status', ProjectStatus::PendingReview)
            ->count();
    }
}
