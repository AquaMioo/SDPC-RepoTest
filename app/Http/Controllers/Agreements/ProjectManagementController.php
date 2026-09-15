<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\SummariseProgress;
use App\Actions\Student\ListStudentApplications;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementTask;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Project Management" — one screen for both sides of a signed agreement.
 *
 * Replaces the student's Workflow and the client's Project Process tabs. The
 * student writes each phase's checklist, checks tasks off with proof and plans
 * the timeline; the client reads all of it and verifies tasks. Progress is the
 * share of tasks the client verified, computed by SummariseProgress — never a
 * number anybody types in.
 *
 * Only available once the two sides are collaborating: the agreement has both
 * signatures and is active. Until then the tab is still there but the page is
 * locked and says why, and every write behind it refuses too (AgreementPolicy).
 *
 * Which side the viewer is on comes from their role, and which agreements they
 * can see from the team in the URL: for a client, the business's; for a
 * student, their own and — when they are on a teammate's team — the one that
 * teammate signed, read-only.
 */
class ProjectManagementController extends Controller
{
    public function __construct(
        private readonly SummariseProgress $summariseProgress,
        private readonly ListStudentApplications $listStudentApplications,
    ) {}

    /**
     * Show the project management screen.
     */
    public function __invoke(Request $request, Team $currentTeam): Response
    {
        $user = $request->user();
        $isStudentSide = $user->isStudent();

        $agreements = $this->visibleAgreements($user, $currentTeam, $isStudentSide);

        /*
         * A client can be running more than one build at once — one agreement
         * per student taken on — so the screen can be pointed at any of them.
         * An id that is not in the visible set falls back to the newest rather
         * than opening somebody else's.
         */
        $selected = $agreements->firstWhere('id', $request->integer('agreement'))
            ?? $agreements->first();

        return Inertia::render('project-management/index', [
            'side' => $isStudentSide ? 'student' : 'client',
            'agreements' => $agreements
                ->map(fn (Agreement $agreement): array => [
                    'id' => $agreement->id,
                    'reference' => $agreement->reference,
                    'projectTitle' => $agreement->project->title,
                    'counterpart' => $this->counterpart($agreement, $isStudentSide),
                ])
                ->values()
                ->all(),
            'agreement' => $selected === null ? null : $this->present($selected, $isStudentSide),
            'can' => [
                'manage' => $selected !== null && Gate::allows('manageTasks', $selected),
                'verify' => $selected !== null && Gate::allows('verifyTasks', $selected),
            ],
            /*
             * Where the locked page points: the agreement still being
             * negotiated, if there is one, so "sign it to unlock this" is one
             * click away.
             */
            'pendingAgreementId' => $selected === null
                ? $this->pendingAgreementId($user, $currentTeam, $isStudentSide)
                : null,
            'applications' => $isStudentSide ? $this->listStudentApplications->handle($user) : [],
        ]);
    }

    /**
     * The active agreements this viewer may open here, newest first.
     *
     * @return Collection<int, Agreement>
     */
    protected function visibleAgreements(User $user, Team $currentTeam, bool $isStudentSide): Collection
    {
        return $this->scopeToViewer(Agreement::query()->active(), $user, $currentTeam, $isStudentSide)
            ->with(['project.team.clientProfile', 'student', 'milestones.tasks.verifier'])
            ->latest('activated_at')
            ->latest('id')
            ->get()
            ->filter(fn (Agreement $agreement): bool => Gate::forUser($user)->allows('viewProgress', $agreement))
            ->values();
    }

    /**
     * The newest agreement still waiting on signatures, for the locked page.
     */
    protected function pendingAgreementId(User $user, Team $currentTeam, bool $isStudentSide): ?int
    {
        $pending = $this->scopeToViewer(Agreement::query()->standing(), $user, $currentTeam, $isStudentSide)
            ->latest('id')
            ->first();

        return $pending !== null && Gate::forUser($user)->allows('view', $pending) ? $pending->id : null;
    }

    /**
     * Narrow an agreement query to the viewer's side of the platform.
     *
     * @param  Builder<Agreement>  $query
     * @return Builder<Agreement>
     */
    protected function scopeToViewer(Builder $query, User $user, Team $currentTeam, bool $isStudentSide): Builder
    {
        if (! $isStudentSide) {
            return $query->where('team_id', $currentTeam->id);
        }

        /*
         * The student themselves, plus whoever owns the team in the URL — on a
         * teammate's team that is the teammate, whose signed build this
         * student may watch.
         */
        $ownerIds = Membership::query()
            ->where('team_id', $currentTeam->id)
            ->where('role', TeamRole::Owner->value)
            ->pluck('user_id');

        return $query->whereIn('student_id', $ownerIds->push($user->id)->unique()->all());
    }

    /**
     * Shape one agreement for the screen.
     *
     * @return array<string, mixed>
     */
    protected function present(Agreement $agreement, bool $isStudentSide): array
    {
        $summary = $this->summariseProgress->handle($agreement);

        $phaseSummaries = collect($summary['phases'])->keyBy('id');

        return [
            'id' => $agreement->id,
            'reference' => $agreement->reference,
            'projectTitle' => $agreement->project->title,
            'counterpart' => $this->counterpart($agreement, $isStudentSide),
            'studentName' => $agreement->student->name,
            'startsOn' => $agreement->starts_on?->toDateString(),
            'endsOn' => $agreement->ends_on?->toDateString(),
            'summary' => collect($summary)->except('phases')->all(),
            'phases' => $agreement->milestones
                ->map(fn (AgreementMilestone $milestone): array => [
                    'id' => $milestone->id,
                    'position' => $milestone->position,
                    'title' => $milestone->title,
                    'description' => $milestone->description,
                    /* What was signed, kept as the baseline the plan is measured against. */
                    'agreedStartsOn' => $milestone->starts_on?->toDateString(),
                    'agreedEndsOn' => $milestone->ends_on?->toDateString(),
                    /* The student's working plan, which the timeline draws. */
                    'startsOn' => $milestone->scheduledStartsOn()?->toDateString(),
                    'endsOn' => $milestone->scheduledEndsOn()?->toDateString(),
                    'isRescheduled' => $milestone->planned_starts_on !== null || $milestone->planned_ends_on !== null,
                    'progress' => $phaseSummaries[$milestone->id]['progress'],
                    'verifiedCount' => $phaseSummaries[$milestone->id]['verifiedCount'],
                    'taskCount' => $phaseSummaries[$milestone->id]['taskCount'],
                    'state' => $phaseSummaries[$milestone->id]['state'],
                    'tasks' => $milestone->tasks
                        ->map(fn (AgreementTask $task): array => $this->presentTask($agreement, $task))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Shape one task for the screen.
     *
     * @return array<string, mixed>
     */
    protected function presentTask(Agreement $agreement, AgreementTask $task): array
    {
        return [
            'id' => $task->id,
            'position' => $task->position,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'statusLabel' => $task->status->label(),
            'proofNote' => $task->proof_note,
            'proofUrl' => $task->proof_url,
            'proofName' => $task->proof_name,
            /* Through the checked route, never a disk URL. */
            'proofHref' => $task->proof_path === null ? null : route('agreements.tasks.proof', [
                'current_team' => request()->route('current_team'),
                'agreement' => $agreement->id,
                'task' => $task->id,
            ]),
            'reviewNote' => $task->review_note,
            'submittedAt' => $task->submitted_at?->format('j M Y, g:i a'),
            'verifiedAt' => $task->verified_at?->format('j M Y, g:i a'),
            'verifiedBy' => $task->verifier?->name,
        ];
    }

    /**
     * Name the other side of the agreement from the viewer's point of view.
     */
    protected function counterpart(Agreement $agreement, bool $isStudentSide): string
    {
        return $isStudentSide
            ? ($agreement->project->team->clientProfile?->business_name ?? $agreement->project->team->name)
            : $agreement->student->name;
    }
}
