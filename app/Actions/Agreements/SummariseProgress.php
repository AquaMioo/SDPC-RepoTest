<?php

namespace App\Actions\Agreements;

use App\Enums\TaskStatus;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementTask;

/**
 * The progress figures every screen draws, worked out once.
 *
 * Project Management, the client dashboard and the student dashboard all read
 * this, so the ring, the phase bars and the current phase cannot drift apart
 * between screens. The percentage is the same count Agreement::progress()
 * makes: tasks the client verified over every task.
 *
 * Phases are equal steps, but inside one a phase with ten tasks moves the
 * overall figure ten times as much as a phase with one — each task is one unit
 * of verified work, which is what "granular" means here.
 */
class SummariseProgress
{
    /** A phase nobody has reached yet. */
    public const PHASE_UPCOMING = 'upcoming';

    /** The first phase that still has work outstanding. */
    public const PHASE_IN_PROGRESS = 'in_progress';

    /** Every task in it verified. */
    public const PHASE_DONE = 'done';

    /**
     * Summarise an agreement's progress.
     *
     * @return array{
     *     progress: int,
     *     verifiedCount: int,
     *     submittedCount: int,
     *     taskCount: int,
     *     currentPhase: array{id: int, title: string}|null,
     *     nextMilestone: array{title: string, dueOn: string|null}|null,
     *     dueOn: string|null,
     *     phases: list<array{id: int, title: string, progress: int, verifiedCount: int, taskCount: int, state: string, isDone: bool}>
     * }
     */
    public function handle(Agreement $agreement): array
    {
        $agreement->loadMissing('milestones.tasks');

        $milestones = $agreement->milestones->sortBy('position')->values();

        /*
         * The first phase not yet finished is the one being worked. A phase
         * with no tasks is not finished — nothing on it has been verified — so
         * a fresh agreement reads "Design" as the current phase, not "done".
         */
        $current = $milestones->first(fn (AgreementMilestone $milestone): bool => ! $this->isComplete($milestone));

        $next = $current === null
            ? null
            : $milestones->first(fn (AgreementMilestone $milestone): bool => $milestone->position > $current->position);

        $taskCount = $agreement->taskCount();
        $verifiedCount = $agreement->verifiedTaskCount();

        return [
            'progress' => $agreement->progress(),
            'verifiedCount' => $verifiedCount,
            'submittedCount' => $milestones->sum(fn (AgreementMilestone $milestone): int => $this->countWithStatus($milestone, TaskStatus::Submitted)),
            'taskCount' => $taskCount,
            'currentPhase' => $current === null ? null : ['id' => $current->id, 'title' => $current->title],
            'nextMilestone' => $next === null ? null : [
                'title' => $next->title,
                'dueOn' => $next->scheduledEndsOn()?->format('j M Y'),
            ],
            'dueOn' => $milestones
                ->map(fn (AgreementMilestone $milestone) => $milestone->scheduledEndsOn())
                ->filter()
                ->max()
                ?->format('j M Y'),
            'phases' => $milestones
                ->map(fn (AgreementMilestone $milestone): array => [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'progress' => $this->phaseProgress($milestone),
                    'verifiedCount' => $this->countWithStatus($milestone, TaskStatus::Verified),
                    'taskCount' => $milestone->tasks->count(),
                    'state' => match (true) {
                        $this->isComplete($milestone) => self::PHASE_DONE,
                        $current !== null && $milestone->id === $current->id => self::PHASE_IN_PROGRESS,
                        default => self::PHASE_UPCOMING,
                    },
                    'isDone' => $this->isComplete($milestone),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The share of one phase's tasks the client verified.
     */
    public function phaseProgress(AgreementMilestone $milestone): int
    {
        $total = $milestone->tasks->count();

        return $total === 0
            ? 0
            : (int) round($this->countWithStatus($milestone, TaskStatus::Verified) / $total * 100);
    }

    /**
     * Whether every task in the phase is verified — and there is at least one.
     */
    public function isComplete(AgreementMilestone $milestone): bool
    {
        return $milestone->tasks->isNotEmpty()
            && $milestone->tasks->every(fn (AgreementTask $task): bool => $task->status === TaskStatus::Verified);
    }

    /**
     * Count a phase's tasks in the given status.
     */
    protected function countWithStatus(AgreementMilestone $milestone, TaskStatus $status): int
    {
        return $milestone->tasks
            ->filter(fn (AgreementTask $task): bool => $task->status === $status)
            ->count();
    }
}
