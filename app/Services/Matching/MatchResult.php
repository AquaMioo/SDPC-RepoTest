<?php

namespace App\Services\Matching;

/**
 * One compatibility verdict, with the reasoning that produced it.
 *
 * The factors are the point. A bare percentage is not something a student can
 * act on; "your Laravel and MySQL cover the whole forecast module" is.
 */
final class MatchResult
{
    /**
     * @param  list<array{label: string, value: int}>  $factors
     * @param  list<string>  $matchedSkills
     * @param  list<string>  $missingSkills
     */
    public function __construct(
        public readonly int $compatibility,
        public readonly array $factors,
        public readonly string $insight,
        public readonly string $recommendation,
        public readonly array $matchedSkills = [],
        public readonly array $missingSkills = [],
    ) {}

    /**
     * Shape the result for the recommendations table's `reason` column.
     *
     * @return array<string, mixed>
     */
    public function toReason(): array
    {
        return [
            'factors' => $this->factors,
            'insight' => $this->insight,
            'recommendation' => $this->recommendation,
            'matchedSkills' => $this->matchedSkills,
            'missingSkills' => $this->missingSkills,
        ];
    }

    /**
     * Get the raw 0-1 score the recommendations table stores.
     */
    public function score(): float
    {
        return round($this->compatibility / 100, 4);
    }
}
