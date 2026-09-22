<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\SyncPhaseStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\ReorderTasksRequest;
use App\Http\Requests\Agreements\ReturnTaskRequest;
use App\Http\Requests\Agreements\SaveTaskRequest;
use App\Http\Requests\Agreements\SubmitTaskRequest;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementTask;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Granular Task Completion.
 *
 * The student writes a phase's checklist and checks items off; the client
 * verifies them or sends them back. Who may do which is decided by
 * AgreementPolicy (manageTasks / verifyTasks), and every one of them also
 * requires the agreement to be active — collaboration has to have started.
 *
 * Every action declares Team $currentTeam first and then the agreement, in URL
 * order, and checks that the task or phase belongs to that agreement: a task
 * id from somebody else's contract 404s instead of resolving.
 *
 * Moves are guarded by the task's current status, not just by who is asking.
 * A task can only be verified once it has been submitted, only edited while it
 * is open, and never un-verified — so progress cannot be manufactured by
 * replaying requests in the wrong order.
 */
class AgreementTaskController extends Controller
{
    /**
     * A phase checklist long enough for any real build, short enough that a
     * script cannot fill the table.
     */
    public const MAX_TASKS_PER_PHASE = 50;

    public function __construct(private readonly SyncPhaseStatus $syncPhaseStatus) {}

    /**
     * Add a task to the end of a phase's checklist.
     */
    public function store(
        SaveTaskRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementMilestone $milestone,
    ): RedirectResponse {
        abort_unless($milestone->agreement_id === $agreement->id, Response::HTTP_NOT_FOUND);

        if ($milestone->tasks()->count() >= self::MAX_TASKS_PER_PHASE) {
            throw ValidationException::withMessages([
                'title' => __('A phase can hold at most :max tasks.', ['max' => self::MAX_TASKS_PER_PHASE]),
            ]);
        }

        $milestone->tasks()->create([
            'position' => (int) $milestone->tasks()->max('position') + 1,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'due_on' => $request->validated('due_on'),
            'status' => TaskStatus::Open,
        ]);

        /* A new task reopens a phase that was complete. */
        $this->syncPhaseStatus->handle($milestone, $request->user());

        return back();
    }

    /**
     * Rename or re-describe a task that has not been handed over.
     */
    public function update(
        SaveTaskRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementTask $task,
    ): RedirectResponse {
        $this->ensureBelongs($task, $agreement);
        $this->ensureStatus($task, TaskStatus::Open, __('Only a task that has not been submitted can be edited.'));

        /*
         * A deadline is set freely once, then only moves with the client's
         * approval — an edit that tried to move or clear it would make the
         * approval step pointless.
         */
        if ($request->has('due_on')
            && $task->due_on !== null
            && $request->validated('due_on') !== $task->due_on->toDateString()) {
            throw ValidationException::withMessages([
                'due_on' => __("A deadline only moves with the client's approval. Ask for a change instead."),
            ]);
        }

        $task->update($request->validated());

        return back();
    }

    /**
     * Delete a task that has not been handed over.
     *
     * Submitted work has to be withdrawn first, and verified work stays: it is
     * the record of what the client accepted.
     */
    public function destroy(
        Request $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementTask $task,
    ): RedirectResponse {
        Gate::authorize('manageTasks', $agreement);

        $this->ensureBelongs($task, $agreement);
        $this->ensureStatus($task, TaskStatus::Open, __('Only a task that has not been submitted can be deleted.'));

        $milestone = $task->milestone;

        $task->forgetProofFile();
        $task->delete();

        /* Removing the last open task can complete a phase. */
        $this->syncPhaseStatus->handle($milestone, $request->user());

        return back();
    }

    /**
     * Put a phase's tasks in the order given.
     */
    public function reorder(
        ReorderTasksRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementMilestone $milestone,
    ): RedirectResponse {
        abort_unless($milestone->agreement_id === $agreement->id, Response::HTTP_NOT_FOUND);

        /** @var list<int> $order */
        $order = array_map(intval(...), $request->validated('task_ids'));

        $existing = $milestone->tasks()->pluck('id')->all();

        /*
         * Exactly this phase's tasks, each once. Anything else is either a
         * stale screen or an id from another phase, and neither may move rows.
         */
        if (count($order) !== count($existing) || array_diff($existing, $order) !== []) {
            throw ValidationException::withMessages([
                'task_ids' => __('The list of tasks is out of date. Reload the page and try again.'),
            ]);
        }

        DB::transaction(function () use ($order): void {
            foreach ($order as $index => $taskId) {
                AgreementTask::query()->whereKey($taskId)->update(['position' => $index + 1]);
            }
        });

        return back();
    }

    /**
     * Check a task off: hand it to the client for review, with proof.
     *
     * This does not complete the task. It moves to "pending client review" and
     * counts for nothing in the progress figure until the client verifies it.
     */
    public function submit(
        SubmitTaskRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementTask $task,
    ): RedirectResponse {
        $this->ensureBelongs($task, $agreement);
        $this->ensureStatus($task, TaskStatus::Open, __('This task has already been submitted.'));

        if ($request->boolean('remove_file') || $request->hasFile('proof_file')) {
            $task->forgetProofFile();
        }

        if ($request->hasFile('proof_file')) {
            $file = $request->file('proof_file');

            $task->forceFill([
                'proof_path' => $file->store('task-proofs/'.$agreement->id, AgreementTask::PROOF_DISK),
                'proof_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            ]);
        }

        $task->forceFill([
            'status' => TaskStatus::Submitted,
            'proof_note' => $request->validated('proof_note'),
            'proof_url' => $request->validated('proof_url'),
            'submitted_at' => now(),
            'submitted_by' => $request->user()->id,
            /* The student has answered the client's note by resubmitting. */
            'review_note' => null,
        ])->save();

        $this->syncPhaseStatus->handle($task->milestone, $request->user());

        return back();
    }

    /**
     * Uncheck a task the client has not reviewed yet.
     *
     * The proof stays attached, so resubmitting does not mean uploading again.
     */
    public function withdraw(
        Request $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementTask $task,
    ): RedirectResponse {
        Gate::authorize('manageTasks', $agreement);

        $this->ensureBelongs($task, $agreement);
        $this->ensureStatus($task, TaskStatus::Submitted, __('Only a task waiting for review can be withdrawn.'));

        $task->update([
            'status' => TaskStatus::Open,
            'submitted_at' => null,
            'submitted_by' => null,
        ]);

        $this->syncPhaseStatus->handle($task->milestone, $request->user());

        return back();
    }

    /**
     * Verify a submitted task: the client confirms it is done.
     *
     * The only move that makes progress.
     */
    public function verify(
        Request $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementTask $task,
    ): RedirectResponse {
        Gate::authorize('verifyTasks', $agreement);

        $this->ensureBelongs($task, $agreement);
        $this->ensureStatus($task, TaskStatus::Submitted, __('Only a task the student has submitted can be verified.'));

        $task->update([
            'status' => TaskStatus::Verified,
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
            'review_note' => null,
        ]);

        $this->syncPhaseStatus->handle($task->milestone, $request->user());

        return back();
    }

    /**
     * Send a submitted task back to the student, with a reason.
     */
    public function sendBack(
        ReturnTaskRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementTask $task,
    ): RedirectResponse {
        $this->ensureBelongs($task, $agreement);
        $this->ensureStatus($task, TaskStatus::Submitted, __('Only a task the student has submitted can be sent back.'));

        $task->update([
            'status' => TaskStatus::Open,
            'review_note' => $request->validated('review_note'),
            'submitted_at' => null,
            'submitted_by' => null,
        ]);

        $this->syncPhaseStatus->handle($task->milestone, $request->user());

        return back();
    }

    /**
     * Serve a task's proof file to somebody allowed to see the project.
     *
     * The file is on the public disk but not published — see
     * config/filesystems.php — so this is the only way to it, behind the same
     * check as the screen that links to it.
     */
    public function proof(
        Request $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementTask $task,
    ): StreamedResponse {
        Gate::authorize('viewProgress', $agreement);

        $this->ensureBelongs($task, $agreement);

        $disk = Storage::disk(AgreementTask::PROOF_DISK);

        abort_if($task->proof_path === null || ! $disk->exists($task->proof_path), Response::HTTP_NOT_FOUND);

        /* private, no-store: a shared cache would hand it to the next reader unchecked. */
        return $disk->response($task->proof_path, $task->proof_name, [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * 404 a task that is not part of the agreement named in the URL.
     */
    protected function ensureBelongs(AgreementTask $task, Agreement $agreement): void
    {
        abort_unless($task->belongsToAgreement($agreement), Response::HTTP_NOT_FOUND);
    }

    /**
     * Refuse a move the task's current status does not allow.
     *
     * A validation error rather than a bare 403, so the screen can say why.
     */
    protected function ensureStatus(AgreementTask $task, TaskStatus $expected, string $message): void
    {
        if ($task->status !== $expected) {
            throw ValidationException::withMessages(['task' => $message]);
        }
    }
}
