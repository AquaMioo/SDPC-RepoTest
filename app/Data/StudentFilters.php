<?php

namespace App\Data;

use Illuminate\Http\Request;

/**
 * The recruit screen's filter state, parsed once from the query string so the
 * controller and the Inertia props read from the same shape.
 */
class StudentFilters
{
    /**
     * @param  list<string>  $skills  Skill slugs, spanning all skill types.
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly array $skills = [],
        public readonly ?string $school = null,
        public readonly ?string $course = null,
        public readonly ?int $yearLevel = null,
        public readonly ?float $minimumRating = null,
        public readonly ?int $minimumCompletedProjects = null,
        public readonly bool $availableOnly = false,
        public readonly string $sort = self::SORT_RECOMMENDED,
    ) {}

    public const SORT_RECOMMENDED = 'recommended';

    public const SORT_RATING = 'rating';

    public const SORT_EXPERIENCE = 'experience';

    public const SORT_NAME = 'name';

    /**
     * Get the sort options offered on the recruit screen.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function sortOptions(): array
    {
        return [
            ['value' => self::SORT_RECOMMENDED, 'label' => 'Recommended'],
            ['value' => self::SORT_RATING, 'label' => 'Highest rated'],
            ['value' => self::SORT_EXPERIENCE, 'label' => 'Most projects completed'],
            ['value' => self::SORT_NAME, 'label' => 'Name (A–Z)'],
        ];
    }

    /**
     * Build the filter set from an incoming request.
     */
    public static function fromRequest(Request $request): self
    {
        $sort = (string) $request->query('sort', self::SORT_RECOMMENDED);

        return new self(
            search: $request->filled('search') ? trim((string) $request->query('search')) : null,
            skills: array_values(array_filter((array) $request->query('skills', []))),
            school: $request->filled('school') ? (string) $request->query('school') : null,
            course: $request->filled('course') ? (string) $request->query('course') : null,
            yearLevel: $request->filled('year_level') ? (int) $request->query('year_level') : null,
            minimumRating: $request->filled('min_rating') ? (float) $request->query('min_rating') : null,
            minimumCompletedProjects: $request->filled('min_projects') ? (int) $request->query('min_projects') : null,
            availableOnly: $request->boolean('available_only'),
            sort: in_array($sort, array_column(self::sortOptions(), 'value'), true)
                ? $sort
                : self::SORT_RECOMMENDED,
        );
    }

    /**
     * Get the filter state as Inertia props.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'skills' => $this->skills,
            'school' => $this->school,
            'course' => $this->course,
            'yearLevel' => $this->yearLevel,
            'minRating' => $this->minimumRating,
            'minProjects' => $this->minimumCompletedProjects,
            'availableOnly' => $this->availableOnly,
            'sort' => $this->sort,
        ];
    }
}
