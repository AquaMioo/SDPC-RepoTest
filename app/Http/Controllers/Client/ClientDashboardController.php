<?php

namespace App\Http\Controllers\Client;

use App\Enums\AgreementStatus;
use App\Enums\ApplicationStatus;
use App\Enums\MilestoneStatus;
use App\Enums\SiteContentKey;
use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Application;
use App\Models\Project;
use App\Models\SiteContent;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClientDashboardController extends Controller
{
    /**
     * Show the client workspace overview.
     *
     * Three panels: calendar, progress, team. The counts, recent-activity feed
     * and shortlist that used to live here were taken off deliberately;
     * applications are read on the Recruit and posting screens, which is where
     * a client acts on them.
     */
    public function __invoke(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('client/dashboard', [
            'userName' => $request->user()->name,
            /*
             * The post button is the one thing on this screen the posting cap
             * can take away, so it has to know before it renders.
             */
            'canPostProject' => Gate::allows('create', Project::class),
            /*
             * Clients no longer pass through the generic dashboard, so the
             * invitation prompt has to travel with them.
             */
            'pendingInvitations' => TeamInvitation::pendingForDashboard($request->user()->email),

            'announcement' => $this->announcement(),

            /*
             * Deferred so the frame paints first: each of these walks the
             * agreement and its milestones.
             */
            'currentProject' => Inertia::defer(fn () => $this->currentProject($team)),
            'projectTeam' => Inertia::defer(fn () => $this->projectTeam($team)),
            'calendarEvents' => Inertia::defer(fn () => $this->calendarEvents($team)),
        ]);
    }

    /**
     * The posting the team is running, with its milestone progress.
     *
     * Progress comes from the signed agreement rather than the posting: the
     * milestones a client tracks are the ones both sides agreed to. Null when
     * there is no live agreement, and the panel says so rather than drawing an
     * empty ring.
     *
     * @return array<string, mixed>|null
     */
    protected function currentProject(Team $team): ?array
    {
        $agreement = Agreement::query()
            ->where('team_id', $team->id)
            ->where('status', AgreementStatus::Active)
            ->with(['project', 'milestones'])
            ->latest('id')
            ->first();

        if ($agreement === null) {
            return null;
        }

        $milestones = $agreement->milestones;

        /*
         * The phase being worked, and the one after it. Reporting the same
         * milestone as both — which happens if you simply take the first
         * unapproved one twice — tells the client nothing.
         */
        $current = $milestones->first(
            fn (AgreementMilestone $milestone): bool => $milestone->status !== MilestoneStatus::Approved,
        );

        $next = $current === null
            ? null
            : $milestones->first(
                fn (AgreementMilestone $milestone): bool => $milestone->position > $current->position,
            );

        return [
            'title' => $agreement->project->title,
            'slug' => $agreement->project->slug,
            'reference' => $agreement->reference,
            'progress' => $agreement->progress(),
            /* What the ring is counting, so it can say so. */
            'approvedCount' => $agreement->approvedMilestoneCount(),
            'milestoneCount' => $milestones->count(),
            'dueOn' => $milestones->max('ends_on')?->format('j M Y'),
            'currentPhase' => $current?->title,
            'nextMilestone' => $next === null ? null : [
                'title' => $next->title,
                'dueOn' => $next->ends_on?->format('j M Y'),
            ],
            'milestones' => $milestones
                ->map(fn (AgreementMilestone $milestone): array => [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'statusLabel' => $milestone->status->label(),
                    'isDone' => $milestone->status === MilestoneStatus::Approved,
                ])
                ->values()
                ->all(),
            'updatedAt' => $agreement->updated_at?->diffForHumans(),
        ];
    }

    /**
     * The students accepted onto the team's postings.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function projectTeam(Team $team): array
    {
        return $this->applications($team)
            ->withStatus(ApplicationStatus::Accepted)
            ->with(['student.studentProfile', 'project'])
            ->get()
            ->map(fn (Application $application): array => [
                'id' => $application->student->id,
                'name' => $application->student->name,
                'avatarUrl' => $application->student->avatarUrl(),
                'role' => $application->student->studentProfile?->headline
                    ?? $application->project->title,
            ])
            ->values()
            ->all();
    }

    /**
     * Dated milestones, so the calendar marks something real.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function calendarEvents(Team $team): array
    {
        return AgreementMilestone::query()
            ->whereHas(
                'agreement',
                fn (Builder $query) => $query
                    ->where('team_id', $team->id)
                    ->where('status', AgreementStatus::Active),
            )
            ->whereNotNull('ends_on')
            ->orderBy('ends_on')
            ->with('agreement.project:id,slug')
            ->get()
            ->map(fn (AgreementMilestone $milestone): array => [
                'id' => $milestone->id,
                'title' => $milestone->title,
                'date' => $milestone->ends_on?->toDateString(),
                'label' => $milestone->ends_on?->format('j M Y'),
                'projectSlug' => $milestone->agreement->project->slug,
                'isDone' => $milestone->status === MilestoneStatus::Approved,
            ])
            ->values()
            ->all();
    }

    /**
     * Get the announcements block an administrator maintains.
     *
     * The same block the student dashboard shows, read from the same row the
     * admin Content screen writes — one piece of copy reaching both modules,
     * rather than two that can drift apart.
     *
     * @return array<string, string|null>|null
     */
    protected function announcement(): ?array
    {
        $block = SiteContent::firstWhere('key', SiteContentKey::Announcements);

        if ($block === null || blank($block->body)) {
            return null;
        }

        return [
            'body' => $block->body,
            'updatedAt' => $block->updated_at?->diffForHumans(),
        ];
    }

    /**
     * Start an application query scoped to the team's postings.
     *
     * @return Builder<Application>
     */
    protected function applications(Team $team)
    {
        return Application::query()
            ->whereHas('project', fn ($query) => $query->where('team_id', $team->id));
    }
}
