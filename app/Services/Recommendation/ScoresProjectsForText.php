<?php

namespace App\Services\Recommendation;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A scorer that can rank open briefs against a capstone somebody describes.
 *
 * The mirror of ScoresFreeText. That one runs text to students, for the
 * client's recruit box; this one runs text to briefs, for the student's
 * advanced search — a capstone title and a couple of sentences about what
 * they are building this term, with no posting and no saved profile involved.
 *
 * Separate contract for the same reason ScoresFreeText is separate: a table
 * of precomputed scores cannot answer a sentence that has never been stored,
 * and StoredRecommendationService should not have to pretend it can. Check
 * for the contract, never for a concrete driver — see .ai/rules/recommendation.md.
 */
interface ScoresProjectsForText
{
    /**
     * Score open briefs against a described capstone, keyed by project id.
     *
     * The student is passed so a driver that cannot read the text has
     * something sensible to fall back to — their saved profile.
     *
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function projectScoresForText(string $title, string $description, User $student): Collection;
}
