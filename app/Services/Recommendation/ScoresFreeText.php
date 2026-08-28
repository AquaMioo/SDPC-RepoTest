<?php

namespace App\Services\Recommendation;

use App\Models\StudentProfile;
use Illuminate\Support\Collection;

/**
 * A scorer that can answer a description of work, not just a saved posting.
 *
 * The recruit search box does two jobs: "Reyes" is a name to filter on, but
 * "POS to my website system" is a description of work with no posting behind
 * it yet. Only some drivers can answer the second kind — a table of
 * precomputed scores cannot, because the brief has never been written.
 *
 * Separate from RecommendationService so that StoredRecommendationService is
 * not forced to pretend. RecruitController checks for this contract to decide
 * whether the search ranks or filters; it used to check for
 * ComputedRecommendationService by name, which quietly disabled the feature
 * the moment a second capable driver existed.
 */
interface ScoresFreeText
{
    /**
     * Score students against free text, e.g. "POS to my website system".
     *
     * @param  Collection<int, StudentProfile>  $students
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function scoresForSearch(string $query, Collection $students): Collection;
}
