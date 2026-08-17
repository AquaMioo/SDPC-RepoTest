<?php

namespace App\Http\Controllers\Student;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Enums\SkillType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ApplyToProjectRequest;
use App\Models\Application;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Team;
use App\Models\User;
use App\Services\Recommendation\RecommendationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * "Get Client" — the board a student browses for work.
 *
 * The mirror of the client module's recruit screen: the client looks for
 * students there, the student looks for postings here.
 */
class ProjectBoardController extends Controller
{
    public const SORT_RECOMMENDED = 'recommended';

    public const SORT_NEWEST = 'newest';

    /** @var list<string> */
    public const SORTS = [self::SORT_RECOMMENDED, self::SORT_NEWEST];

    /**
     * List the postings this student is allowed to see.
     */
    public function index(Request $request, RecommendationService $recommendations): Response
    {
        $student = $request->user();
        $search = trim((string) $request->query('search', ''));
        $skills = array_filter((array) $request->query('skills', []));
        $sort = in_array($request->query('sort'), self::SORTS, true)
            ? $request->query('sort')
            : self::SORT_RECOMMENDED;

        $applied = $this->appliedProjectIds($student);
        $scores = $recommendations->scoresForStudent($student);

        /*
         * The board lists work you could still take. A posting already under
         * way stays reachable by URL — Workflow links back to it — but it does
         * not belong on a page headed "find a client".
         */
        $projects = $this->visibleTo($student)
            ->where('applications_open', true)
            ->where('status', ProjectStatus::Open)
            ->when($search !== '', fn (Builder $query) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")))
            ->when($skills, function (Builder $query, array $slugs) {
                foreach ($slugs as $slug) {
                    $query->whereHas('skills', fn (Builder $skill) => $skill->where('slug', $slug));
                }
            })
            ->with(['team.clientProfile', 'skills'])
            ->withCount('applications')
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Project $project) => $this->toCard($project, $applied, $scores));

        return Inertia::render('student/find-clients', [
            'projects' => $projects,
            'filters' => [
                'search' => $search,
                'skills' => array_values($skills),
                'sort' => $sort,
            ],
            'sorts' => [
                ['value' => self::SORT_RECOMMENDED, 'label' => 'Recommended'],
                ['value' => self::SORT_NEWEST, 'label' => 'Newest'],
            ],
            'skillGroups' => $this->skillGroups(),
            'canApply' => $student->isVerifiedForOperating(),
            /*
             * Drives the analysis panel. True only when something was actually
             * scored on this request — a brief with nothing to go on gets no
             * ring rather than a zeroed-out one that reads as a failed match.
             */
            'matchingEnabled' => $scores->isNotEmpty(),
            'highlight' => $this->highlight($projects->getCollection(), $scores),
        ]);
    }

    /**
     * Build the AI analysis panel for the student's strongest match.
     *
     * Returns null whenever there is nothing scored to talk about, which is
     * every case until the AI module starts writing recommendations.
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
            'title' => $best['title'],
            'client' => $best['client'],
            'compatibility' => $best['compatibility'],
            /*
             * The per-factor bars. The AI module owns the shape of `reason`;
             * anything that is not a {label, value} pair is skipped rather
             * than guessed at.
             */
            'factors' => collect($reason['factors'] ?? [])
                ->filter(fn ($factor) => is_array($factor)
                    && isset($factor['label'])
                    && is_numeric($factor['value'] ?? null))
                ->map(fn (array $factor) => [
                    'label' => (string) $factor['label'],
                    'value' => (int) $factor['value'],
                ])
                ->values()
                ->all(),
            'recommendation' => $reason['recommendation'] ?? null,
        ];
    }

    /**
     * Show one posting in full.
     */
    public function show(Request $request, Team $currentTeam, Project $project): Response
    {
        $student = $request->user();

        abort_unless($this->isVisibleTo($project, $student), HttpResponse::HTTP_NOT_FOUND);

        $project->load(['team.clientProfile', 'skills']);

        $application = Application::query()
            ->where('project_id', $project->id)
            ->where('user_id', $student->id)
            ->first();

        return Inertia::render('student/project', [
            'project' => [
                ...$this->toCard($project, $this->appliedProjectIds($student), null),
                'description' => $project->description,
                'objectives' => $project->objectives,
                'businessDescription' => $project->team->clientProfile?->business_description,
                'city' => $project->team->clientProfile?->city,
            ],
            'application' => $application === null ? null : [
                'status' => $application->status->value,
                'statusLabel' => $application->status->label(),
                'appliedAt' => $application->created_at?->format('j M Y'),
            ],
            'canApply' => $student->isVerifiedForOperating(),
        ]);
    }

    /**
     * Apply to a posting.
     */
    public function apply(ApplyToProjectRequest $request, Team $currentTeam, Project $project): RedirectResponse
    {
        $student = $request->user();

        abort_unless($this->isVisibleTo($project, $student), HttpResponse::HTTP_NOT_FOUND);

        if (! $project->isAcceptingApplications()) {
            throw ValidationException::withMessages([
                'application' => 'This posting is no longer taking applications.',
            ]);
        }

        /*
         * The table's unique index would catch this too, but a duplicate here
         * is an ordinary "you already applied", not a 500.
         */
        $exists = Application::query()
            ->where('project_id', $project->id)
            ->where('user_id', $student->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'application' => 'You have already applied to this posting.',
            ]);
        }

        Application::create([
            ...$request->validated(),
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
            'source' => ApplicationSource::Applied,
        ]);

        return back()->with('success', 'Your application has been sent.');
    }

    /**
     * Withdraw an application the client has not resolved yet.
     */
    public function withdraw(Request $request, Team $currentTeam, Application $application): RedirectResponse
    {
        abort_unless($application->user_id === $request->user()->id, HttpResponse::HTTP_FORBIDDEN);

        if (! $application->status->isActionable()) {
            throw ValidationException::withMessages([
                'application' => 'This application has already been decided.',
            ]);
        }

        $application->update(['status' => ApplicationStatus::Withdrawn]);

        return back()->with('success', 'Your application has been withdrawn.');
    }

    /**
     * Build the query of postings a given student may see.
     *
     * @return Builder<Project>
     */
    protected function visibleTo(User $student): Builder
    {
        /*
         * Every approved posting, for every student. Audience targeting was
         * removed from the posting form, so a brief's status is now the only
         * thing deciding whether it is on the board — there is no longer such
         * a thing as an invite-only or school-restricted posting.
         */
        return Project::query()->publiclyVisible();
    }

    /**
     * Determine if one posting is on this student's board.
     */
    protected function isVisibleTo(Project $project, User $student): bool
    {
        return $this->visibleTo($student)->whereKey($project->getKey())->exists();
    }

    /**
     * Shape a posting for the board.
     *
     * @return array<string, mixed>
     */
    protected function toCard(Project $project, Collection $applied, ?Collection $scores = null): array
    {
        $score = $scores?->get($project->id);

        return [
            'id' => $project->id,
            'slug' => $project->slug,
            'title' => $project->title,
            'summary' => str($project->description)->limit(150)->toString(),
            'postedAt' => $project->published_at?->diffForHumans(short: true),
            'compatibility' => $score['compatibility'] ?? null,
            'insight' => $score['reason']['insight'] ?? null,
            'category' => $project->category,
            'industry' => $project->industry,
            'client' => $project->team->clientProfile?->business_name ?? $project->team->name,
            'skills' => $project->skills->pluck('name')->all(),
            'applicants' => $project->applications_count ?? $project->applications()->count(),
            'isAcceptingApplications' => $project->isAcceptingApplications(),
            'hasApplied' => $applied->has($project->id),
        ];
    }

    /**
     * Get the ids of every project this student has already applied to.
     *
     * Fetched once per request and looked up per card — asking per row turns
     * a twelve-card page into thirteen queries.
     *
     * @return Collection<int, bool>
     */
    protected function appliedProjectIds(User $student): Collection
    {
        return Application::query()
            ->where('user_id', $student->id)
            ->pluck('project_id')
            ->flip()
            ->map(fn () => true);
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
