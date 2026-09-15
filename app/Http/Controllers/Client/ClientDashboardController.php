<?php

namespace App\Http\Controllers\Client;

use App\Actions\Agreements\SummariseProgress;
use App\Actions\Messaging\UpcomingMeetings;
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
    public function __invoke(Request $request, UpcomingMeetings $upcomingMeetings): Response
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
            /*
             * Meetings booked in any thread this team can still open. Not
             * deferred: it is one small query, and a meeting starting in ten
             * minutes is the last thing that should arrive after a skeleton.
             */
            'upcomingMeetings' => $upcomingMeetings->handle($request->user()),
        ]);
    }

    /**
     * The posting the team is running, with its task progress.
     *
     * Progress comes from the signed agreement rather than the posting, and
     * is the same Granular Task Completion figure Project Management shows —
     * both read SummariseProgress. Null when there is no live agreement, and
     * the panel says so rather than drawing an empty ring.
     *
     * @return array<string, mixed>|null
     */
    protected function currentProject(Team $team): ?array
    {
        $agreement = Agreement::query()
            ->where('team_id', $team->id)
            ->where('status', AgreementStatus::Active)
            ->with(['project', 'milestones.tasks'])
            ->latest('id')
            ->first();

        if ($agreement === null) {
            return null;
        }

        $summary = app(SummariseProgress::class)->handle($agreement);

        /*
         * Work moves on the checklist far more often than the contract row,
         * so "last updated" is whichever changed most recently.
         */
        $lastTaskChange = $agreement->milestones
            ->flatMap(fn (AgreementMilestone $milestone) => $milestone->tasks)
            ->max('updated_at');

        $updatedAt = collect([$agreement->updated_at, $lastTaskChange])->filter()->max();

        return [
            'agreementId' => $agreement->id,
            'title' => $agreement->project->title,
            'slug' => $agreement->project->slug,
            'reference' => $agreement->reference,
            'progress' => $summary['progress'],
            /* What the ring is counting, so it can say so. */
            'verifiedCount' => $summary['verifiedCount'],
            'submittedCount' => $summary['submittedCount'],
            'taskCount' => $summary['taskCount'],
            'dueOn' => $summary['dueOn'],
            'currentPhase' => $summary['currentPhase']['title'] ?? null,
            'nextMilestone' => $summary['nextMilestone'],
            'milestones' => $summary['phases'],
            'updatedAt' => $updatedAt?->diffForHumans(),
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
                /* Presence, not membership: see User::isOnline(). */
                'isOnline' => $application->student->isOnline(),
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
        /*
         * The student's working schedule where they have moved a phase on the
         * timeline, the agreed date where they have not — the calendar shows
         * when the work is actually expected, the same dates Project
         * Management draws.
         */
        return AgreementMilestone::query()
            ->whereHas(
                'agreement',
                fn (Builder $query) => $query
                    ->where('team_id', $team->id)
                    ->where('status', AgreementStatus::Active),
            )
            ->where(fn (Builder $query) => $query->whereNotNull('ends_on')->orWhereNotNull('planned_ends_on'))
            ->with('agreement.project:id,slug')
            ->get()
            ->sortBy(fn (AgreementMilestone $milestone): string => (string) $milestone->scheduledEndsOn()?->toDateString())
            ->map(fn (AgreementMilestone $milestone): array => [
                'id' => $milestone->id,
                'title' => $milestone->title,
                'date' => $milestone->scheduledEndsOn()?->toDateString(),
                'label' => $milestone->scheduledEndsOn()?->format('j M Y'),
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
