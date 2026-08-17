<?php

namespace App\Services\Matching;

use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * What is being matched against: a posting, or the words a client typed.
 *
 * Both sides of the platform reduce to this. A client searching "POS for my
 * store" and a published brief describing the same thing produce the same
 * shape, so one scorer serves both directions.
 */
final class ScopeProfile
{
    /**
     * @param  Collection<int, string>  $requiredSkills  Skill slugs the brief names outright.
     * @param  Collection<int, string>  $inferredSkills  Skill slugs read out of the prose.
     * @param  Collection<int, string>  $phrases  The domain words that drove the inference.
     */
    public function __construct(
        public readonly string $text,
        public readonly Collection $requiredSkills,
        public readonly Collection $inferredSkills,
        public readonly Collection $phrases,
        public readonly ?string $title = null,
    ) {}

    /**
     * Build a scope from a published posting.
     */
    public static function fromProject(Project $project, SkillInference $inference): self
    {
        $text = implode(' ', array_filter([
            $project->title,
            $project->category,
            $project->industry,
            $project->description,
            $project->objectives,
        ]));

        return new self(
            text: $text,
            requiredSkills: $project->relationLoaded('skills')
                ? $project->skills->pluck('slug')
                : $project->skills()->pluck('slug'),
            inferredSkills: $inference->fromText($text),
            phrases: $inference->phrasesIn($text),
            title: $project->title,
        );
    }

    /**
     * Build a scope from a free-text search, e.g. "POS to my website system".
     */
    public static function fromSearch(string $query, SkillInference $inference): self
    {
        return new self(
            text: $query,
            requiredSkills: collect(),
            inferredSkills: $inference->fromText($query),
            phrases: $inference->phrasesIn($query),
        );
    }

    /**
     * Get every skill this scope calls for, named or inferred.
     *
     * @return Collection<int, string>
     */
    public function allSkills(): Collection
    {
        return $this->requiredSkills->merge($this->inferredSkills)->unique()->values();
    }

    /**
     * Determine if there is enough here to say anything useful.
     *
     * A scope nobody described and no skill was named cannot rank anyone, and
     * a confident-looking percentage built on nothing is worse than silence.
     */
    public function isMeaningful(): bool
    {
        return $this->allSkills()->isNotEmpty();
    }
}
