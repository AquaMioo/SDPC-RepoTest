<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\RespondToInvitation;
use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\IssueCategory;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ApplyToProjectRequest;
use App\Models\Application;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\Recommendation\RecommendationService;
use App\Services\Recommendation\ScoresProjectsForText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
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
     * Briefs to a page.
     *
     * Five, and a footer to reach the rest. Both halves matter: the model
     * scores the whole board before paging (see rankedByScore), so the page
     * size is only ever about how much a reader is asked to take in at once.
     */
    private const PER_PAGE = 5;

    /**
     * List the postings this student is allowed to see.
     */
    public function index(Request $request, RecommendationService $recommendations): Response
    {
        $student = $request->user();
        $search = trim((string) $request->query('search', ''));
        $sort = in_array($request->query('sort'), self::SORTS, true)
            ? $request->query('sort')
            : self::SORT_RECOMMENDED;

        $applied = $this->appliedProjectIds($student);

        /*
         * The advanced search. A student describes the capstone they are
         * building this term and the board ranks against that instead of
         * against their saved profile — the case a skill list cannot answer,
         * because what you are building is not always what your profile says
         * you know. Falls back to the profile ranking when the driver cannot
         * read text, so the board is never worse off for having asked.
         */
        $capstone = [
            'title' => trim((string) $request->query('capstone_title', '')),
            'description' => trim((string) $request->query('capstone_description', '')),
        ];
        $hasCapstone = $capstone['title'] !== '' || $capstone['description'] !== '';

        $scores = $hasCapstone && $recommendations instanceof ScoresProjectsForText
            ? $recommendations->projectScoresForText($capstone['title'], $capstone['description'], $student)
            : $recommendations->scoresForStudent($student);

        /*
         * The board lists work you could still take. A posting already under
         * way stays reachable by URL — Workflow links back to it — but it does
         * not belong on a page headed "find a client".
         */
        $query = $this->visibleTo($student)
            ->where('applications_open', true)
            ->where('status', ProjectStatus::Open)
            ->when($search !== '', fn (Builder $query) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")))
            ->with('team.clientProfile')
            ->withCount('applications');

        /*
         * "Recommended" only means something once there are scores to rank by.
         * Until then — and whenever the student asks for newest — the board is
         * ordered by the database, which is both cheaper and the only honest
         * order available.
         */
        $projects = $sort === self::SORT_RECOMMENDED && $scores->isNotEmpty()
            ? $this->rankedByScore($query, $scores, $request)
            : $query->latest('published_at')->paginate(self::PER_PAGE)->withQueryString();

        $projects->through(fn (Project $project) => $this->toCard($project, $applied, $scores));

        return Inertia::render('student/find-clients', [
            'projects' => $projects,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
            ],
            /*
             * Echoed back so the dialog reopens with what was typed, and the
             * board can say it is ranking against a capstone rather than a
             * profile without the screen having to guess.
             */
            'capstone' => $capstone,
            'sorts' => [
                ['value' => self::SORT_RECOMMENDED, 'label' => 'Recommended'],
                ['value' => self::SORT_NEWEST, 'label' => 'Newest'],
            ],
            'canApply' => $student->isVerifiedForOperating(),
            /* One build at a time, so the board says so before they try. */
            'holdsProjectInHand' => $student->holdsProjectInHand(),
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
     * Order the board by how well each posting fits this student.
     *
     * Scored before paging rather than after, or "recommended" would only mean
     * "best on whichever page you happen to be looking at" — the same reason
     * RecruitController ranks before it pages. Published date breaks ties so
     * two equally good fits still come back in a stable order.
     *
     * @param  Builder<Project>  $query
     * @param  Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>  $scores
     * @return LengthAwarePaginator<int, Project>
     */
    protected function rankedByScore(Builder $query, Collection $scores, Request $request): LengthAwarePaginator
    {
        $ranked = $query->get()
            ->sortByDesc(fn (Project $project): array => [
                $scores->get($project->id)['compatibility'] ?? -1,
                $project->published_at?->getTimestamp() ?? 0,
            ])
            ->values();

        $perPage = self::PER_PAGE;
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

        $project->load(['team.clientProfile']);

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
            'holdsProjectInHand' => $student->holdsProjectInHand(),
            /*
             * Reporting a posting is open to any signed in student, verified
             * or not — a misleading listing is worth hearing about before the
             * reporter's own credential has been read.
             */
            'projectId' => $project->id,
            'reportCategories' => IssueCategory::postingOptions(),
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

        /* One student, one build. See User::holdsProjectInHand(). */
        if ($student->holdsProjectInHand()) {
            throw ValidationException::withMessages([
                'application' => 'You already have a project in hand. Finish it before taking on another.',
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
     * Take up an invitation a client sent.
     *
     * The mirror of the client accepting an application: whoever did not open
     * the conversation is the one who answers it.
     */
    public function acceptInvitation(
        Request $request,
        Team $currentTeam,
        Application $application,
        RespondToInvitation $respondToInvitation,
    ): RedirectResponse {
        Gate::authorize('acceptInvitation', $application);

        $respondToInvitation->accept($application);

        return back()->with('success', 'Invitation accepted. Your agreement is ready to review.');
    }

    /**
     * Turn an invitation down.
     */
    public function declineInvitation(
        Request $request,
        Team $currentTeam,
        Application $application,
        RespondToInvitation $respondToInvitation,
    ): RedirectResponse {
        Gate::authorize('declineInvitation', $application);

        $respondToInvitation->decline($application);

        return back()->with('success', 'Invitation declined.');
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
            /* Where the business is, when it has said. */
            'city' => $project->team->clientProfile?->city,
            /*
             * The permit an administrator accepted, shown as a badge. It is
             * the same check that decides whether a client may post at all,
             * so a listing on this board is always from a verified business —
             * saying so out loud is what a student is looking for.
             */
            'isBusinessVerified' => $project->team->clientProfile?->isVerified() ?? false,
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
}
