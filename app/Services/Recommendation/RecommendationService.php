<?php

namespace App\Services\Recommendation;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The seam the AI module will implement.
 *
 * The Client Module only ever reads through this contract, so scoring can be
 * swapped in later without touching the recruit screen or its controller.
 */
interface RecommendationService
{
    /**
     * Get recommendation scores for a project, keyed by student user id.
     *
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function scoresFor(Project $project): Collection;

    /**
     * Get recommendation scores for a student, keyed by project id.
     *
     * The same rows read from the other end: a recommendation is a
     * project-and-student pair, so the student's board can rank briefs against
     * them without a second table or a second scoring pass.
     *
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function scoresForStudent(User $student): Collection;

    /**
     * Determine if real scoring is available.
     *
     * The recruit screen hides its match panel when this is false rather than
     * showing zeroed-out bars that look like a failed match.
     */
    public function isEnabled(): bool;
}
