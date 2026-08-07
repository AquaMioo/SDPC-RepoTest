<?php

namespace App\Http\Controllers\Client;

use App\Data\StudentFilters;
use App\Enums\SkillType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Project;
use App\Models\School;
use App\Models\Skill;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Recommendation\RecommendationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecruitController extends Controller
{
    /**
     * Discover student developers, optionally ranked against a posting.
     */
    public function __invoke(Request $request, RecommendationService $recommendations): Response
    {
        $filters = StudentFilters::fromRequest($request);
        $project = $this->contextProject($request);

        $scores = $project !== null
            ? $recommendations->scoresFor($project)
            : collect();

        $students = $this->query($filters)
            ->paginate(24)
            ->withQueryString()
            ->through(fn (StudentProfile $profile) => $this->toCard($profile, $scores->get($profile->user_id)));

        return Inertia::render('client/recruit', [
            'students' => $students,
            'filters' => $filters->toArray(),
            'options' => [
                'sorts' => StudentFilters::sortOptions(),
                'schools' => School::query()->orderBy('name')->get(['slug', 'name']),
                'courses' => Course::query()->orderBy('name')->get(['slug', 'name', 'abbreviation']),
                'skillGroups' => $this->skillGroups(),
            ],
            'context' => $project === null ? null : [
                'slug' => $project->slug,
                'title' => $project->title,
            ],
            'matchingEnabled' => $recommendations->isEnabled(),
        ]);
    }

    /**
     * Build the filtered student query.
     *
     * @return Builder<StudentProfile>
     */
    protected function query(StudentFilters $filters): Builder
    {
        return StudentProfile::query()
            ->with(['user', 'school', 'course', 'skills'])
            ->when($filters->availableOnly, fn (Builder $query) => $query->available())
            ->when($filters->search, fn (Builder $query, string $search) => $query
                ->where(fn (Builder $inner) => $inner
                    ->whereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"))
                    ->orWhere('headline', 'like', "%{$search}%")
                    ->orWhereHas('skills', fn (Builder $skill) => $skill->where('name', 'like', "%{$search}%"))))
            ->when($filters->school, fn (Builder $query, string $slug) => $query
                ->whereHas('school', fn (Builder $school) => $school->where('slug', $slug)))
            ->when($filters->course, fn (Builder $query, string $slug) => $query
                ->whereHas('course', fn (Builder $course) => $course->where('slug', $slug)))
            ->when($filters->yearLevel, fn (Builder $query, int $level) => $query->where('year_level', $level))
            ->when($filters->minimumRating, fn (Builder $query, float $rating) => $query->where('rating_average', '>=', $rating))
            ->when($filters->minimumCompletedProjects, fn (Builder $query, int $count) => $query
                ->where('completed_projects_count', '>=', $count))
            /**
             * Every selected skill must be present, not just any of them —
             * a partial match is not what the filter chips imply.
             */
            ->when($filters->skills, function (Builder $query, array $slugs) {
                foreach ($slugs as $slug) {
                    $query->whereHas('skills', fn (Builder $skill) => $skill->where('slug', $slug));
                }
            })
            ->when($filters->sort === StudentFilters::SORT_RATING,
                fn (Builder $query) => $query->orderByDesc('rating_average'))
            ->when($filters->sort === StudentFilters::SORT_EXPERIENCE,
                fn (Builder $query) => $query->orderByDesc('completed_projects_count'))
            ->when($filters->sort === StudentFilters::SORT_NAME,
                fn (Builder $query) => $query->orderBy(
                    User::select('name')->whereColumn('users.id', 'student_profiles.user_id'),
                ))
            /**
             * Recommended has no scores to sort by yet, so it falls back to a
             * stable, sensible default rather than an arbitrary id order.
             */
            ->when($filters->sort === StudentFilters::SORT_RECOMMENDED, fn (Builder $query) => $query
                ->orderByDesc('rating_average')
                ->orderByDesc('completed_projects_count'))
            ->orderBy('id');
    }

    /**
     * Resolve the posting the recruiter is filtering against, if any.
     */
    protected function contextProject(Request $request): ?Project
    {
        if (! $request->filled('project')) {
            return null;
        }

        return Project::query()
            ->forTeam($request->user()->currentTeam)
            ->where('slug', $request->query('project'))
            ->first();
    }

    /**
     * Shape a student for the recruit grid.
     *
     * @param  array{score: float, compatibility: int, reason: array<string, mixed>}|null  $score
     * @return array<string, mixed>
     */
    protected function toCard(StudentProfile $profile, ?array $score): array
    {
        return [
            'id' => $profile->user_id,
            'name' => $profile->user->name,
            'headline' => $profile->headline,
            'school' => $profile->school?->name,
            'course' => $profile->course?->abbreviation ?? $profile->course?->name,
            'yearLevel' => $profile->year_level,
            'rating' => (float) $profile->rating_average,
            'completedProjects' => $profile->completed_projects_count,
            'hourlyRate' => $profile->hourly_rate,
            'isAvailable' => $profile->is_available,
            'skills' => $profile->skills->take(4)->pluck('name'),
            'compatibility' => $score['compatibility'] ?? null,
        ];
    }

    /**
     * Get the skill filter options grouped by type.
     *
     * @return array<array{type: string, label: string, skills: mixed}>
     */
    protected function skillGroups(): array
    {
        $skills = Skill::query()->orderBy('name')->get(['slug', 'name', 'type'])->groupBy('type');

        return collect(SkillType::cases())
            ->map(fn (SkillType $type) => [
                'type' => $type->value,
                'label' => $type->groupLabel(),
                'skills' => $skills->get($type->value, collect())->map->only(['slug', 'name'])->values(),
            ])
            ->filter(fn (array $group) => $group['skills']->isNotEmpty())
            ->values()
            ->all();
    }
}
