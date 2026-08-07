<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\DuplicateProject;
use App\Actions\Client\SaveProject;
use App\Enums\BudgetType;
use App\Enums\ExperienceLevel;
use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SaveProjectRequest;
use App\Models\Course;
use App\Models\Project;
use App\Models\School;
use App\Models\Skill;
use App\Models\Team;
use App\Notifications\Client\ProjectStatusChanged;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * List the acting team's postings.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $projects = Project::query()
            ->forTeam($request->user()->currentTeam)
            ->with('skills')
            ->withCount([
                'applications',
                'applications as pending_applications_count' => fn ($query) => $query->awaitingDecision(),
            ])
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Project $project) => $this->toListItem($project));

        return Inertia::render('client/projects/index', [
            'projects' => $projects,
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Show the posting form.
     */
    public function create(): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('client/projects/create', [
            'options' => $this->formOptions(),
        ]);
    }

    /**
     * Store a new posting.
     */
    public function store(SaveProjectRequest $request, SaveProject $saveProject): RedirectResponse
    {
        $project = $saveProject->create(
            $request->user()->currentTeam,
            $request->user(),
            $request->validated(),
        );

        return to_route('projects.show', $project)
            ->with('success', $project->status === ProjectStatus::Draft
                ? 'Draft saved.'
                : 'Project submitted for review.');
    }

    /**
     * Show a single posting.
     */
    public function show(Team $currentTeam, Project $project): Response
    {
        Gate::authorize('view', $project);

        $project->load(['skills', 'milestones', 'attachments', 'creator', 'preferredSchool', 'preferredCourse']);

        return Inertia::render('client/projects/show', [
            'project' => $this->toDetail($project),
            'applicantCounts' => [
                'total' => $project->applications()->count(),
                'pending' => $project->applications()->awaitingDecision()->count(),
                'accepted' => $project->members()->count(),
            ],
        ]);
    }

    /**
     * Show the edit form for a posting.
     */
    public function edit(Team $currentTeam, Project $project): Response
    {
        Gate::authorize('update', $project);

        $project->load(['skills', 'milestones']);

        return Inertia::render('client/projects/edit', [
            'project' => $this->toDetail($project),
            'options' => $this->formOptions(),
        ]);
    }

    /**
     * Update a posting.
     */
    public function update(SaveProjectRequest $request, Team $currentTeam, Project $project, SaveProject $saveProject): RedirectResponse
    {
        $saveProject->update($project, $request->validated());

        return to_route('projects.show', $project)->with('success', 'Project updated.');
    }

    /**
     * Archive a posting without deleting it.
     */
    public function archive(Team $currentTeam, Project $project): RedirectResponse
    {
        Gate::authorize('archive', $project);

        $previousStatus = $project->status;

        $project->update([
            'status' => ProjectStatus::Archived,
            'applications_open' => false,
        ]);

        Notification::send(
            $project->team->members,
            new ProjectStatusChanged($project->refresh(), $previousStatus),
        );

        return back()->with('success', 'Project archived.');
    }

    /**
     * Copy a posting into a new draft.
     */
    public function duplicate(Request $request, Team $currentTeam, Project $project, DuplicateProject $duplicateProject): RedirectResponse
    {
        Gate::authorize('duplicate', $project);

        $copy = $duplicateProject->handle($project, $request->user());

        return to_route('projects.edit', $copy)->with('success', 'Draft created from an existing posting.');
    }

    /**
     * Pause or resume applications on a posting.
     */
    public function toggleApplications(Team $currentTeam, Project $project): RedirectResponse
    {
        Gate::authorize('toggleApplications', $project);

        $project->update(['applications_open' => ! $project->applications_open]);

        return back()->with('success', $project->applications_open
            ? 'Applications reopened.'
            : 'Applications closed.');
    }

    /**
     * Delete a posting.
     */
    public function destroy(Team $currentTeam, Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return to_route('projects.index')->with('success', 'Project deleted.');
    }

    /**
     * Shape a project for the board listing.
     *
     * @return array<string, mixed>
     */
    protected function toListItem(Project $project): array
    {
        return [
            'slug' => $project->slug,
            'title' => $project->title,
            'category' => $project->category,
            'status' => $project->status->value,
            'statusLabel' => $project->status->label(),
            'budgetAmount' => $project->hide_budget ? null : $project->budget_amount,
            'applicationsOpen' => $project->isAcceptingApplications(),
            'applicationsCount' => $project->applications_count,
            'pendingApplicationsCount' => $project->pending_applications_count,
            'targetDeliveryDate' => $project->target_delivery_date?->toDateString(),
            'skills' => $project->skills->pluck('name'),
        ];
    }

    /**
     * Shape a project for the detail and edit screens.
     *
     * @return array<string, mixed>
     */
    protected function toDetail(Project $project): array
    {
        return [
            'slug' => $project->slug,
            'title' => $project->title,
            'description' => $project->description,
            'objectives' => $project->objectives,
            'category' => $project->category,
            'industry' => $project->industry,
            'status' => $project->status->value,
            'statusLabel' => $project->status->label(),
            'isEditable' => $project->status->isEditable(),
            'budgetType' => $project->budget_type->value,
            'budgetAmount' => $project->budget_amount,
            'hideBudget' => $project->hide_budget,
            'startDate' => $project->start_date?->toDateString(),
            'targetDeliveryDate' => $project->target_delivery_date?->toDateString(),
            'applicationDeadline' => $project->application_deadline?->toDateString(),
            'expectedCompletionDate' => $project->expected_completion_date?->toDateString(),
            'weeklyCommitment' => $project->weekly_commitment,
            'teamSize' => $project->team_size,
            'experienceLevel' => $project->experience_level->value,
            'openToCapstoneGroups' => $project->open_to_capstone_groups,
            'visibility' => $project->visibility->value,
            'preferredSchoolId' => $project->preferred_school_id,
            'preferredCourseId' => $project->preferred_course_id,
            'preferredYearLevel' => $project->preferred_year_level,
            'applicationsOpen' => $project->applications_open,
            'isAcceptingApplications' => $project->isAcceptingApplications(),
            'skills' => $project->skills->pluck('name'),
            'milestones' => $project->milestones->map(fn ($milestone) => [
                'title' => $milestone->title,
                'dueDate' => $milestone->due_date?->toDateString(),
                'amount' => $milestone->amount,
                'isCompleted' => $milestone->isCompleted(),
            ]),
            'attachments' => $project->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'size' => $attachment->humanSize(),
            ]),
            'publishedAt' => $project->published_at?->toDateTimeString(),
        ];
    }

    /**
     * Get the lookup data the posting form's selects need.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'budgetTypes' => BudgetType::options(),
            'experienceLevels' => ExperienceLevel::options(),
            'visibilities' => ProjectVisibility::options(),
            'schools' => School::query()->orderBy('name')->get(['id', 'name']),
            'courses' => Course::query()->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'skills' => Skill::query()->orderBy('name')->get(['name', 'type']),
        ];
    }

    /**
     * Get the status filter options for the board.
     *
     * @return array<array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return collect(ProjectStatus::cases())
            ->map(fn (ProjectStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->all();
    }
}
