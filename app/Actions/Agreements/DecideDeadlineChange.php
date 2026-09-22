<?php

namespace App\Actions\Agreements;

use App\Enums\DeadlineRequestStatus;
use App\Models\AgreementMilestone;
use App\Models\AgreementTask;
use App\Models\DeadlineChangeRequest;
use App\Models\User;
use App\Notifications\Agreements\DeadlineChangeDecided;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The client answers an ask to move a deadline.
 *
 * Approving is the only way a deadline moves once it is set: a task's due_on,
 * or — for the final deadline — the Turnover phase's planned end. The signed
 * dates (starts_on / ends_on) are never touched; they stay as the baseline the
 * plan is measured against. Declining leaves everything where it was.
 *
 * The rules are checked again at approval, because the other dates may have
 * moved since the ask was made.
 */
class DecideDeadlineChange
{
    public function __construct(private readonly RequestDeadlineChange $requestDeadlineChange) {}

    /**
     * Approve the ask and move the deadline.
     */
    public function approve(DeadlineChangeRequest $request, User $client, ?string $note): DeadlineChangeRequest
    {
        $decided = DB::transaction(function () use ($request, $client, $note): DeadlineChangeRequest {
            $request = $this->lockPending($request);
            $agreement = $request->agreement->load('milestones');

            if ($request->isForFinalDeadline()) {
                $latestTask = $this->requestDeadlineChange->latestTaskDeadline($agreement);

                if ($latestTask !== null && $request->proposed_on->lt($latestTask)) {
                    $this->refuse(__('A task is now due on :date, after the date asked for. Decline this and ask the student for a new one.', [
                        'date' => $latestTask->format('j M Y'),
                    ]));
                }

                AgreementMilestone::query()
                    ->whereKey($request->agreement_milestone_id)
                    ->update(['planned_ends_on' => $request->proposed_on->toDateString()]);
            } else {
                $finalDeadline = $agreement->finalDeadline();

                if ($finalDeadline !== null && $request->proposed_on->gt($finalDeadline)) {
                    $this->refuse(__('The date asked for is now after the final deadline, :date.', [
                        'date' => $finalDeadline->format('j M Y'),
                    ]));
                }

                AgreementTask::query()
                    ->whereKey($request->agreement_task_id)
                    ->update(['due_on' => $request->proposed_on->toDateString()]);
            }

            return $this->close($request, DeadlineRequestStatus::Approved, $client, $note);
        });

        $this->announce($decided);

        return $decided;
    }

    /**
     * Decline the ask; the deadline stays.
     */
    public function decline(DeadlineChangeRequest $request, User $client, ?string $note): DeadlineChangeRequest
    {
        $decided = DB::transaction(fn (): DeadlineChangeRequest => $this->close(
            $this->lockPending($request),
            DeadlineRequestStatus::Declined,
            $client,
            $note,
        ));

        $this->announce($decided);

        return $decided;
    }

    /**
     * Re-read the ask under a lock and make sure nobody decided it already.
     */
    protected function lockPending(DeadlineChangeRequest $request): DeadlineChangeRequest
    {
        $request = DeadlineChangeRequest::query()->lockForUpdate()->findOrFail($request->id);

        if ($request->status !== DeadlineRequestStatus::Pending) {
            $this->refuse(__('This change was already :status.', ['status' => strtolower($request->status->label())]));
        }

        return $request;
    }

    /**
     * Record the answer on the ask.
     */
    protected function close(DeadlineChangeRequest $request, DeadlineRequestStatus $status, User $client, ?string $note): DeadlineChangeRequest
    {
        $request->update([
            'status' => $status,
            'decided_by' => $client->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        return $request;
    }

    /**
     * Tell the student side how it went.
     */
    protected function announce(DeadlineChangeRequest $request): void
    {
        $request->loadMissing('agreement.project', 'task', 'milestone');

        Notification::send($request->agreement->studentSide(), new DeadlineChangeDecided($request));
    }

    /**
     * Refuse with a reason the screen can show.
     *
     * @throws ValidationException
     */
    protected function refuse(string $message): never
    {
        throw ValidationException::withMessages(['deadline' => $message]);
    }
}
