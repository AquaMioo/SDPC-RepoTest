<?php

namespace App\Services\Matching;

use App\Models\StudentProfile;
use Illuminate\Support\Collection;

/**
 * Scores a student against a scope.
 *
 * One engine serves both directions because both reduce to a ScopeProfile and
 * a StudentProfile: the client's recruit search scores many students against
 * one scope, the student's board scores many scopes against one student.
 *
 * The weights are deliberate and visible rather than tuned into opacity. Skill
 * coverage dominates because it is the thing that decides whether the work can
 * actually be done; everything else adjusts around it.
 */
class MatchingEngine
{
    /*
     * A posting states nothing about experience, headcount, budget or dates
     * any more, so what it asks for is entirely its words and its skills. The
     * weights below redistribute what the removed preference factor carried.
     */

    /** Skills the brief names outright — the strongest signal there is. */
    protected const WEIGHT_REQUIRED = 45;

    /** Skills read out of the prose, e.g. "POS" implying payment integration. */
    protected const WEIGHT_SCOPE = 30;

    /** Whether the student is open to work at all. */
    protected const WEIGHT_AVAILABILITY = 12;

    /** Rating and finished projects. */
    protected const WEIGHT_TRACK_RECORD = 13;

    public function __construct(protected SkillInference $inference) {}

    /**
     * Score one student against one scope.
     */
    public function score(ScopeProfile $scope, StudentProfile $student): MatchResult
    {
        $studentSkills = $student->relationLoaded('skills')
            ? $student->skills->pluck('slug')
            : $student->skills()->pluck('slug');

        $required = $this->coverage($scope->requiredSkills, $studentSkills);
        $inferred = $this->coverage($scope->inferredSkills, $studentSkills);
        $availability = $student->is_available ? 100 : 0;
        $track = $this->trackRecord($student);

        $compatibility = (int) round(
            ($required * self::WEIGHT_REQUIRED
                + $inferred * self::WEIGHT_SCOPE
                + $availability * self::WEIGHT_AVAILABILITY
                + $track * self::WEIGHT_TRACK_RECORD) / 100
        );

        $matched = $scope->allSkills()->intersect($studentSkills);
        $missing = $scope->allSkills()->diff($studentSkills);

        return new MatchResult(
            compatibility: $compatibility,
            factors: $this->factors($scope, $required, $inferred, $availability, $track),
            insight: $this->insight($scope, $matched, $missing, $student),
            recommendation: $this->recommendation($scope, $matched, $missing, $compatibility),
            matchedSkills: $this->names($matched),
            missingSkills: $this->names($missing),
        );
    }

    /**
     * Get the share of wanted skills a student actually has, 0-100.
     *
     * An empty want list scores neutral rather than perfect: having nothing
     * asked of you is not the same as meeting every requirement.
     *
     * @param  Collection<int, string>  $wanted
     * @param  Collection<int, string>  $held
     */
    protected function coverage(Collection $wanted, Collection $held): float
    {
        if ($wanted->isEmpty()) {
            return 50.0;
        }

        return $wanted->intersect($held)->count() / $wanted->count() * 100;
    }

    /**
     * Score rating and finished projects into one 0-100 figure.
     */
    protected function trackRecord(StudentProfile $student): float
    {
        $rating = (float) $student->rating_average;
        $completed = min($student->completed_projects_count, 5);

        /*
         * A student with no history sits at the middle rather than the floor.
         * Everyone starts with nothing, and a platform for students that ranks
         * newcomers last would never place a first-timer.
         */
        if ($rating <= 0 && $completed === 0) {
            return 50.0;
        }

        return min(100, ($rating / 5 * 60) + ($completed / 5 * 40));
    }

    /**
     * Build the per-factor bars the match panel renders.
     *
     * @return list<array{label: string, value: int}>
     */
    protected function factors(
        ScopeProfile $scope,
        float $required,
        float $inferred,
        float $availability,
        float $track,
    ): array {
        $factors = [];

        if ($scope->requiredSkills->isNotEmpty()) {
            $factors[] = ['label' => 'Required skills', 'value' => (int) round($required)];
        }

        if ($scope->inferredSkills->isNotEmpty()) {
            $factors[] = ['label' => 'Scope fit', 'value' => (int) round($inferred)];
        }

        $factors[] = ['label' => 'Availability this term', 'value' => (int) round($availability)];
        $factors[] = ['label' => 'Track record', 'value' => (int) round($track)];

        return $factors;
    }

    /**
     * Write the one-line insight that sits on a card.
     *
     * @param  Collection<int, string>  $matched
     * @param  Collection<int, string>  $missing
     */
    protected function insight(
        ScopeProfile $scope,
        Collection $matched,
        Collection $missing,
        StudentProfile $student,
    ): string {
        if ($matched->isEmpty()) {
            return 'No overlap with the skills this brief calls for yet.';
        }

        $names = $this->names($matched->take(3));
        $covered = $this->readable($names);

        if ($missing->isEmpty()) {
            return "Covers everything this brief asks for, including {$covered}.";
        }

        $gap = $this->readable($this->names($missing->take(2)));

        return "Strong on {$covered}; would be picking up {$gap}.";
    }

    /**
     * Write the strategic paragraph beside the match ring.
     *
     * @param  Collection<int, string>  $matched
     * @param  Collection<int, string>  $missing
     */
    protected function recommendation(
        ScopeProfile $scope,
        Collection $matched,
        Collection $missing,
        int $compatibility,
    ): string {
        $subject = $scope->title !== null ? "“{$scope->title}”" : 'this scope';

        if ($compatibility >= 80) {
            $lead = $this->readable($this->names($matched->take(2)));

            return "Lead with your {$lead} work — it maps almost one to one onto {$subject}.";
        }

        if ($compatibility >= 55) {
            $gap = $missing->isEmpty()
                ? 'the delivery timeline'
                : $this->readable($this->names($missing->take(2)));

            return "A workable fit for {$subject}. Be explicit about how you would approach {$gap}.";
        }

        if ($scope->phrases->isNotEmpty()) {
            $asked = $this->readable($scope->phrases->take(2)->all());

            return "{$subject} is mostly about {$asked}, which sits outside the work on this profile.";
        }

        return "Little overlap with {$subject} on the evidence available.";
    }

    /**
     * Turn skill slugs into the names a person reads.
     *
     * @param  Collection<int, string>  $slugs
     * @return list<string>
     */
    protected function names(Collection $slugs): array
    {
        return $slugs
            ->map(fn (string $slug) => str($slug)->replace('-', ' ')->title()->toString())
            ->values()
            ->all();
    }

    /**
     * Join a short list the way a sentence would.
     *
     * @param  list<string>  $items
     */
    protected function readable(array $items): string
    {
        if ($items === []) {
            return 'this work';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }
}
