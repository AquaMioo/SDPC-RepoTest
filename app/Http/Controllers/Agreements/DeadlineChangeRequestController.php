<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\DecideDeadlineChange;
use App\Actions\Agreements\RequestDeadlineChange;
use App\Enums\DeadlineRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\DecideDeadlineChangeRequest;
use App\Http\Requests\Agreements\RequestDeadlineChangeRequest;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementTask;
use App\Models\DeadlineChangeRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deadlines are flexible, but watched.
 *
 * The student side sets a task's deadline once when writing it. After that it
 * — and the final deadline, the end of Turnover — moves only here: the
 * student side asks, the client approves or declines. Approving is what moves
 * the date; see App\Actions\Agreements\DecideDeadlineChange.
 *
 * Like every Project Management route, each action declares Team $currentTeam
 * first and then the agreement, in URL order, and 404s on a task, phase or
 * request that belongs to some other agreement.
 */
class DeadlineChangeRequestController extends Controller
{
    /**
     * Ask to move a task's deadline.
     */
    public function storeForTask(
        RequestDeadlineChangeRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementTask $task,
        RequestDeadlineChange $requestDeadlineChange,
    ): RedirectResponse {
        abort_unless($task->belongsToAgreement($agreement), Response::HTTP_NOT_FOUND);

        $requestDeadlineChange->forTask(
            $agreement,
            $task,
            $request->user(),
            $request->validated('proposed_on'),
            $request->validated('reason'),
        );

        return back()->with('success', __('Asked the client to move the deadline.'));
    }

    /**
     * Ask to move the final deadline.
     */
    public function storeForFinalDeadline(
        RequestDeadlineChangeRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementMilestone $milestone,
        RequestDeadlineChange $requestDeadlineChange,
    ): RedirectResponse {
        abort_unless($milestone->agreement_id === $agreement->id, Response::HTTP_NOT_FOUND);

        $requestDeadlineChange->forFinalDeadline(
            $agreement,
            $milestone,
            $request->user(),
            $request->validated('proposed_on'),
            $request->validated('reason'),
        );

        return back()->with('success', __('Asked the client to move the final deadline.'));
    }

    /**
     * Approve an ask and move the deadline.
     */
    public function approve(
        DecideDeadlineChangeRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        DeadlineChangeRequest $deadlineRequest,
        DecideDeadlineChange $decideDeadlineChange,
    ): RedirectResponse {
        abort_unless($deadlineRequest->agreement_id === $agreement->id, Response::HTTP_NOT_FOUND);

        $decideDeadlineChange->approve($deadlineRequest, $request->user(), $request->validated('decision_note'));

        return back()->with('success', __('Deadline moved.'));
    }

    /**
     * Decline an ask; the deadline stays.
     */
    public function decline(
        DecideDeadlineChangeRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        DeadlineChangeRequest $deadlineRequest,
        DecideDeadlineChange $decideDeadlineChange,
    ): RedirectResponse {
        abort_unless($deadlineRequest->agreement_id === $agreement->id, Response::HTTP_NOT_FOUND);

        $decideDeadlineChange->decline($deadlineRequest, $request->user(), $request->validated('decision_note'));

        return back()->with('success', __('Deadline kept.'));
    }

    /**
     * Take an ask back before the client decides it.
     */
    public function destroy(
        Request $request,
        Team $currentTeam,
        Agreement $agreement,
        DeadlineChangeRequest $deadlineRequest,
    ): RedirectResponse {
        Gate::authorize('manageTasks', $agreement);

        abort_unless($deadlineRequest->agreement_id === $agreement->id, Response::HTTP_NOT_FOUND);

        if ($deadlineRequest->status !== DeadlineRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'deadline' => __('Only a change still waiting for the client can be taken back.'),
            ]);
        }

        $deadlineRequest->update(['status' => DeadlineRequestStatus::Withdrawn]);

        return back();
    }
}
