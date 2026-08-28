<?php

namespace App\Http\Controllers\Client;

use App\Data\StudentFilters;
use App\Enums\SkillType;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Course;
use App\Models\Project;
use App\Models\School;
use App\Models\Skill;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use App\Services\Matching\ScopeProfile;
use App\Services\Matching\SkillInference;
use App\Services\Recommendation\RecommendationService;
use App\Services\Recommendation\ScoresFreeText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
        $scope = $this->scope($project, $filters->search);

        /*
         * A search box that does two jobs. "Reyes" is a name to filter on, but
         * "POS to my website system" is a description of work — matching that
         * against names and headlines finds nobody, which made the whole
         * feature unreachable. When the words describe a scope, they rank
         * instead of filter.
         */
        $isScopeSearch = $project === null
            && $scope !== null
            && $recommendations instanceof ScoresFreeText;

        $students = $isScopeSearch
            ? $this->rankedByScope($filters, $scope, $recommendations, $request)
            : $this->query($filters)->paginate(24)->withQueryString();

        $scores = match (true) {
            $project !== null => $recommendations->scoresFor($project),
            $isScopeSearch => $recommendations->scoresForSearch($filters->search, $students->getCollection()),
            default => collect(),
        };

        $threads = $this->openThreads($request->user()->currentTeam, $students->getCollection());

        $students->through(fn (StudentProfile $profile) => $this->toCard(
            $profile,
            $scores->get($profile->user_id),
            $threads->get($profile->user_id),
        ));

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
            /* True only when something was actually scored on this request. */
            'matchingEnabled' => $scores->isNotEmpty(),
            'scopeSkills' => $this->scopeSkills($project, $filters->search),
            'highlight' => $this->highlight($students->getCollection(), $scores),
        ]);
    }

    /**
     * Describe the strongest match on the page, for the matching rail.
     *
     * The per-factor bars are the half of the answer a single percentage
     * cannot give. Returns null whenever nothing on this page was scored, so
     * the rail is absent rather than showing zeroed-out bars that read as a
     * failed match.
     *
     * @param  Collection<int, array<string, mixed>>  $cards
     * @param  Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>  $scores
     * @return array<string, mixed>|null
     */
    protected function highlight(Collection $cards, Collection $scores): ?array
    {
        $best = $cards
            ->filter(fn (array $card): bool => $card['compatibility'] !== null)
            ->sortByDesc('compatibility')
            ->first();

        if ($best === null) {
            return null;
        }

        $reason = $scores->get($best['id'])['reason'] ?? [];

        return [
            'name' => $best['name'],
            'compatibility' => $best['compatibility'],
            'factors' => $reason['factors'] ?? [],
            'recommendation' => $reason['recommendation'] ?? null,
            'matchedSkills' => $reason['matchedSkills'] ?? [],
        ];
    }

    /**
     * Build the filtered student query.
     *
     * @return Builder<StudentProfile>
     */
    protected function query(StudentFilters $filters, bool $applySearch = true): Builder
    {
        return StudentProfile::query()
            /*
             * The credential and the third-party check are both read by
             * User::isVerifiedStudent(), which the row draws a badge off —
             * without them that is two queries per student on the page.
             */
            ->with([
                'user.latestStudentCredential',
                'user.studentVerifications',
                'school',
                'course',
                'skills',
                'portfolioItems',
            ])
            ->when($filters->availableOnly, fn (Builder $query) => $query->available())
            ->when($applySearch ? $filters->search : null, fn (Builder $query, string $search) => $query
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
     * Resolve the scope being matched against, if there is one.
     */
    protected function scope(?Project $project, ?string $search): ?ScopeProfile
    {
        $inference = app(SkillInference::class);

        $scope = match (true) {
            $project !== null => ScopeProfile::fromProject($project, $inference),
            $search !== null => ScopeProfile::fromSearch($search, $inference),
            default => null,
        };

        return $scope?->isMeaningful() === true ? $scope : null;
    }

    /**
     * Rank students against a described scope, best fit first.
     *
     * Scoring happens before paging rather than after, or "best match" would
     * only mean "best on whichever page you happen to be looking at".
     *
     * @return LengthAwarePaginator<int, StudentProfile>
     */
    protected function rankedByScope(
        StudentFilters $filters,
        ScopeProfile $scope,
        /*
         * The contract, not a driver. This read ComputedRecommendationService
         * and __invoke() had already been changed to test for ScoresFreeText,
         * so the moment a second capable driver was bound the scope search
         * died with a TypeError instead of ranking. See
         * .ai/rules/recommendation.md — the same trap, one call site later.
         */
        ScoresFreeText $recommendations,
        Request $request,
    ): LengthAwarePaginator {
        $candidates = $this->query($filters, applySearch: false)
            /*
             * Anyone holding at least one skill the scope calls for. Without
             * this every student on the platform is a candidate, and the ones
             * scoring near zero drown the ones who can do the work.
             */
            ->whereHas('skills', fn (Builder $skill) => $skill->whereIn('slug', $scope->allSkills()))
            ->get();

        $scores = $recommendations->scoresForSearch($scope->text, $candidates);

        $ranked = $candidates
            ->sortByDesc(fn (StudentProfile $profile) => $scores->get($profile->user_id)['compatibility'] ?? 0)
            ->values();

        $perPage = 24;
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginator(
            $ranked->forPage($page, $perPage)->values(),
            $ranked->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * Get the skills the current scope implies, for the client to see.
     *
     * This is the half of the answer a percentage cannot give: searching "POS
     * for my store" should say out loud that building one means payment
     * integration and MySQL, so the client knows what they are shopping for.
     *
     * @return list<array{name: string, slug: string, isRequired: bool}>
     */
    protected function scopeSkills(?Project $project, ?string $search): array
    {
        $inference = app(SkillInference::class);

        $scope = match (true) {
            $project !== null => ScopeProfile::fromProject($project, $inference),
            $search !== null => ScopeProfile::fromSearch($search, $inference),
            default => null,
        };

        if ($scope === null || ! $scope->isMeaningful()) {
            return [];
        }

        $required = $scope->requiredSkills->flip();

        return Skill::query()
            ->whereIn('slug', $scope->allSkills())
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (Skill $skill) => [
                'slug' => $skill->slug,
                'name' => $skill->name,
                'isRequired' => $required->has($skill->slug),
            ])
            ->values()
            ->all();
    }

    /**
     * Map each listed student to a posting they can already be messaged through.
     *
     * A conversation needs an applications row behind it, so the grid's Message
     * button is only live for students this team has already invited or hired.
     * Without this the button would 403 for almost everyone on the page.
     *
     * @param  EloquentCollection<int, StudentProfile>  $profiles
     * @return Collection<int, int>
     */
    protected function openThreads(Team $team, EloquentCollection $profiles): Collection
    {
        if ($profiles->isEmpty()) {
            return collect();
        }

        return Application::query()
            ->whereIn('user_id', $profiles->pluck('user_id'))
            ->whereHas('project', fn (Builder $query) => $query->where('team_id', $team->id))
            /* Ascending, so the newest row is the one left standing per student. */
            ->orderBy('id')
            ->pluck('project_id', 'user_id');
    }

    /**
     * Shape a student for the recruit grid.
     *
     * @param  array{score: float, compatibility: int, reason: array<string, mixed>}|null  $score
     * @return array<string, mixed>
     */
    protected function toCard(StudentProfile $profile, ?array $score, ?int $messageableProjectId = null): array
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
            /*
             * Presentation only, and deliberately so: what a student may
             * actually do still answers to isVerifiedForOperating().
             */
            'isVerified' => $profile->user->isVerifiedStudent(),
            'location' => $profile->displayLocation(),
            /*
             * Work they have actually documented. The design put two lines of
             * proof under each name; these are the only ones the database can
             * stand behind, so an empty portfolio simply shows none.
             */
            'highlights' => $profile->portfolioItems
                ->sortByDesc('is_featured')
                ->sortBy('position')
                ->take(2)
                ->pluck('title')
                ->values()
                ->all(),
            /*
             * No rate on the card. The work is a capstone build, and what the
             * arrangement is gets settled between the two of them in messages,
             * not advertised as an hourly price on a grid.
             */
            'isAvailable' => $profile->is_available,
            'skills' => $profile->skills->take(4)->pluck('name'),
            /** Null means the card sends them to the profile to invite first. */
            'messageableProjectId' => $messageableProjectId,
            'compatibility' => $score['compatibility'] ?? null,
            /*
             * The reasoning, not just the number. A percentage on its own
             * gives a client nothing to decide with.
             */
            'insight' => $score['reason']['insight'] ?? null,
            'matchedSkills' => $score['reason']['matchedSkills'] ?? [],
            'missingSkills' => $score['reason']['missingSkills'] ?? [],
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
