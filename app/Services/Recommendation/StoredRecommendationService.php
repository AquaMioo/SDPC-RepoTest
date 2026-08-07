<?php

namespace App\Services\Recommendation;

use App\Models\Project;
use App\Models\Recommendation;
use Illuminate\Support\Collection;

/**
 * Reads whatever the recommendations table already holds.
 *
 * Nothing in the Client Module writes to that table, so today this returns an
 * empty set and the recruit screen falls back to a deterministic ordering.
 * When the AI module starts writing scores, this class serves them with no
 * further changes here.
 */
class StoredRecommendationService implements RecommendationService
{
    /**
     * Get recommendation scores for a project, keyed by student user id.
     *
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function scoresFor(Project $project): Collection
    {
        return Recommendation::query()
            ->where('project_id', $project->id)
            ->get()
            ->mapWithKeys(fn (Recommendation $recommendation) => [
                $recommendation->user_id => [
                    'score' => (float) $recommendation->score,
                    'compatibility' => $recommendation->compatibility_percentage,
                    'reason' => $recommendation->reason ?? [],
                ],
            ]);
    }

    /**
     * Determine if real scoring is available.
     */
    public function isEnabled(): bool
    {
        return Recommendation::query()->exists();
    }
}
