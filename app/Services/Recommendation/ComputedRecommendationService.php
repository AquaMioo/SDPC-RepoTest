<?php

namespace App\Services\Recommendation;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Matching\MatchingEngine;
use App\Services\Matching\MatchResult;
use App\Services\Matching\ScopeProfile;
use App\Services\Matching\SkillInference;
use Illuminate\Support\Collection;

/**
 * Scores matches on the spot rather than reading stored rows.
 *
 * Computing per request is the right trade here: a client's recruit search
 * changes with every word they type, so a precomputed table could never answer
 * "who can build a POS" — it only knows about postings that already exist.
 *
 * Cheap enough to do inline: the work is set intersection over a page of
 * students, with no external call and nothing to keep warm.
 */
class ComputedRecommendationService implements RecommendationService
{
    public function __construct(
        protected MatchingEngine $engine,
        protected SkillInference $inference,
    ) {}

    /**
     * Score every student against a posting, keyed by student user id.
     *
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function scoresFor(Project $project): Collection
    {
        $scope = ScopeProfile::fromProject($project, $this->inference);

        if (! $scope->isMeaningful()) {
            return collect();
        }

        return StudentProfile::query()
            ->with('skills')
            ->get()
            ->mapWithKeys(fn (StudentProfile $profile) => [
                $profile->user_id => $this->shape($this->engine->score($scope, $profile)),
            ]);
    }

    /**
     * Score every open posting against a student, keyed by project id.
     *
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function scoresForStudent(User $student): Collection
    {
        $profile = $student->studentProfile;

        if ($profile === null) {
            return collect();
        }

        $profile->loadMissing('skills');

        return Project::query()
            ->where('status', ProjectStatus::Open)
            ->with('skills')
            ->get()
            ->mapWithKeys(function (Project $project) use ($profile) {
                $scope = ScopeProfile::fromProject($project, $this->inference);

                if (! $scope->isMeaningful()) {
                    return [];
                }

                return [$project->id => $this->shape($this->engine->score($scope, $profile))];
            });
    }

    /**
     * Score students against free text, e.g. "POS to my website system".
     *
     * The recruit search calls this: the client has not written a brief yet,
     * only described what they want built.
     *
     * @param  Collection<int, StudentProfile>  $students
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function scoresForSearch(string $query, Collection $students): Collection
    {
        $scope = ScopeProfile::fromSearch($query, $this->inference);

        if (! $scope->isMeaningful()) {
            return collect();
        }

        return $students->mapWithKeys(fn (StudentProfile $profile) => [
            $profile->user_id => $this->shape($this->engine->score($scope, $profile)),
        ]);
    }

    /**
     * Real scoring is always available — it needs no model and no network.
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Shape a result the way the recommendations table and screens expect.
     *
     * @return array{score: float, compatibility: int, reason: array<string, mixed>}
     */
    protected function shape(MatchResult $result): array
    {
        return [
            'score' => $result->score(),
            'compatibility' => $result->compatibility,
            'reason' => $result->toReason(),
        ];
    }
}
