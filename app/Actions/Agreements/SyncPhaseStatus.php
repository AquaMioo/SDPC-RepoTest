<?php

namespace App\Actions\Agreements;

use App\Actions\Billing\RecordTransaction;
use App\Enums\MilestoneStatus;
use App\Enums\TaskStatus;
use App\Models\AgreementMilestone;
use App\Models\AgreementTask;
use App\Models\User;

/**
 * Keeps a phase's status in step with its checklist.
 *
 * The milestone status is read all over the platform — the client calendar,
 * the messaging panel, the contract screen, the ledger — so rather than teach
 * every one of them about tasks, the status is derived from the tasks whenever
 * one changes:
 *
 *   no tasks, or none handed over      → Pending
 *   every outstanding task handed over → Submitted ("in review")
 *   some work handed over or verified  → In progress
 *   every task verified                → Approved
 *
 * Approval still goes through RecordTransaction, which is a no-op while billing
 * is off and never bills the same milestone twice, so a phase that reopens
 * (the student adds a task) and is approved again does not charge again.
 */
class SyncPhaseStatus
{
    public function __construct(private readonly RecordTransaction $recordTransaction) {}

    /**
     * Recompute the phase's status after a change to its tasks.
     *
     * @param  User|null  $actor  Who caused the change; recorded as the approver when it completes the phase.
     */
    public function handle(AgreementMilestone $milestone, ?User $actor = null): void
    {
        $tasks = $milestone->tasks()->get();

        $status = $this->statusFor($tasks->all());

        if ($status === $milestone->status) {
            return;
        }

        $becameApproved = $status === MilestoneStatus::Approved;

        $milestone->update([
            'status' => $status,
            'submitted_at' => $status === MilestoneStatus::Submitted ? now() : $milestone->submitted_at,
            'approved_at' => $becameApproved ? now() : null,
            'approved_by' => $becameApproved ? $actor?->id : null,
        ]);

        if ($becameApproved) {
            $this->recordTransaction->forMilestone($milestone->refresh());
        }
    }

    /**
     * The status a set of tasks puts their phase in.
     *
     * @param  list<AgreementTask>  $tasks
     */
    public function statusFor(array $tasks): MilestoneStatus
    {
        if ($tasks === []) {
            return MilestoneStatus::Pending;
        }

        $counts = collect($tasks)->countBy(fn (AgreementTask $task): string => $task->status->value);

        $verified = $counts->get(TaskStatus::Verified->value, 0);
        $submitted = $counts->get(TaskStatus::Submitted->value, 0);
        $open = $counts->get(TaskStatus::Open->value, 0);

        return match (true) {
            $open === 0 && $submitted === 0 => MilestoneStatus::Approved,
            $open === 0 => MilestoneStatus::Submitted,
            $verified > 0 || $submitted > 0 => MilestoneStatus::InProgress,
            default => MilestoneStatus::Pending,
        };
    }
}
