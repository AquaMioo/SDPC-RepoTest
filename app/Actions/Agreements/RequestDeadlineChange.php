<?php

namespace App\Actions\Agreements;

use App\Enums\DeadlineRequestStatus;
use App\Enums\TaskStatus;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementTask;
use App\Models\DeadlineChangeRequest;
use App\Models\User;
use App\Notifications\Agreements\DeadlineChangeRequested;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The student side asks the client to move a deadline.
 *
 * Nothing moves yet: the ask waits as a Pending DeadlineChangeRequest until the
 * client approves or declines it (DecideDeadlineChange). A deadline carries at
 * most one pending ask, so the client is never answering two versions of the
 * same question.
 *
 * Refusals are ValidationExceptions on 'proposed_on' so the dialog can say why.
 */
class RequestDeadlineChange
{
    /**
     * Ask to move a task's deadline.
     */
    public function forTask(Agreement $agreement, AgreementTask $task, User $student, string $proposedOn, ?string $reason): DeadlineChangeRequest
    {
        $proposed = Carbon::parse($proposedOn);

        $request = DB::transaction(function () use ($agreement, $task, $student, $proposed, $reason): DeadlineChangeRequest {
            /* Locked, so two quick asks cannot both find no pending one. */
            $task = AgreementTask::query()->lockForUpdate()->findOrFail($task->id);

            if ($task->status === TaskStatus::Verified) {
                $this->refuse(__('This task is already verified, so its deadline no longer matters.'));
            }

            if ($task->due_on === null) {
                $this->refuse(__('This task has no deadline yet. Set one by editing the task.'));
            }

            if ($task->due_on->isSameDay($proposed)) {
                $this->refuse(__('That is the deadline it already has.'));
            }

            $finalDeadline = $agreement->loadMissing('milestones')->finalDeadline();

            if ($finalDeadline !== null && $proposed->gt($finalDeadline)) {
                $this->refuse(__('A task has to be due on or before the final deadline, :date.', [
                    'date' => $finalDeadline->format('j M Y'),
                ]));
            }

            if ($task->deadlineRequests()->pending()->exists()) {
                $this->refuse(__('A change to this deadline is already waiting for the client.'));
            }

            return $task->deadlineRequests()->create([
                'agreement_id' => $agreement->id,
                'requested_by' => $student->id,
                'previous_on' => $task->due_on,
                'proposed_on' => $proposed,
                'reason' => $reason,
                'status' => DeadlineRequestStatus::Pending,
            ]);
        });

        $this->announce($agreement, $request);

        return $request;
    }

    /**
     * Ask to move the final deadline: the end of the Turnover phase.
     */
    public function forFinalDeadline(Agreement $agreement, AgreementMilestone $turnover, User $student, string $proposedOn, ?string $reason): DeadlineChangeRequest
    {
        $proposed = Carbon::parse($proposedOn);

        $request = DB::transaction(function () use ($agreement, $turnover, $student, $proposed, $reason): DeadlineChangeRequest {
            $turnover = AgreementMilestone::query()->lockForUpdate()->findOrFail($turnover->id);

            if (! $turnover->isTurnover()) {
                $this->refuse(__('Only the final deadline — the end of Turnover — is changed this way.'));
            }

            $current = $turnover->scheduledEndsOn();

            if ($current !== null && $current->isSameDay($proposed)) {
                $this->refuse(__('That is the final deadline already.'));
            }

            $turnoverStarts = $turnover->scheduledStartsOn();

            if ($turnoverStarts !== null && $proposed->lt($turnoverStarts)) {
                $this->refuse(__('The final deadline cannot come before Turnover starts on :date.', [
                    'date' => $turnoverStarts->format('j M Y'),
                ]));
            }

            $latestTask = $this->latestTaskDeadline($agreement);

            if ($latestTask !== null && $proposed->lt($latestTask)) {
                $this->refuse(__('A task is due on :date, so the final deadline cannot come before it.', [
                    'date' => $latestTask->format('j M Y'),
                ]));
            }

            if ($turnover->deadlineRequests()->pending()->exists()) {
                $this->refuse(__('A change to the final deadline is already waiting for the client.'));
            }

            return $turnover->deadlineRequests()->create([
                'agreement_id' => $agreement->id,
                'requested_by' => $student->id,
                'previous_on' => $current,
                'proposed_on' => $proposed,
                'reason' => $reason,
                'status' => DeadlineRequestStatus::Pending,
            ]);
        });

        $this->announce($agreement, $request);

        return $request;
    }

    /**
     * The latest deadline any task on the agreement carries.
     */
    public function latestTaskDeadline(Agreement $agreement): ?CarbonInterface
    {
        $latest = AgreementTask::query()
            ->whereIn('agreement_milestone_id', $agreement->milestones()->select('id'))
            ->max('due_on');

        return $latest === null ? null : Carbon::parse($latest);
    }

    /**
     * Tell the business there is a date to decide on.
     */
    protected function announce(Agreement $agreement, DeadlineChangeRequest $request): void
    {
        Notification::send($agreement->team->members, new DeadlineChangeRequested($request));
    }

    /**
     * Refuse the ask with a reason the dialog can show.
     *
     * @throws ValidationException
     */
    protected function refuse(string $message): never
    {
        throw ValidationException::withMessages(['proposed_on' => $message]);
    }
}
