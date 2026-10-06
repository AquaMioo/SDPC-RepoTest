<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Objective and Scope become one phase, "Objective & Scope" (owner, 2026-10-07).
 *
 * Each Section VII service is now one task: its Objective the title, its Scope
 * the description (App\Actions\Agreements\SeedServiceTasks). The phase carries
 * no dates and its tasks no deadlines; the timeline is Turnover's dates only.
 *
 * Every SDPC agreement still open or running is moved to that shape. Completed
 * ones keep their three phases as the record of what was delivered.
 *
 * On a running build the Objective task and the Scope task of each service are
 * merged into one, and nothing handed over is lost: a Scope task's submission
 * moves onto its Objective task when that one has none, and when both were
 * submitted the Scope task is kept beside it as an item of its own. Any other
 * Scope task the student added moves across too.
 */
return new class extends Migration
{
    /**
     * Submission columns that move with a Scope task's work.
     *
     * @var list<string>
     */
    private const SUBMISSION = [
        'status', 'proof_note', 'proof_url', 'proof_path', 'proof_name',
        'submitted_at', 'submitted_by', 'verified_at', 'verified_by', 'review_note',
    ];

    public function up(): void
    {
        $agreements = DB::table('agreements')
            ->where('template', 'sdpc_moa')
            ->whereIn('status', ['draft', 'awaiting_signatures', 'active'])
            ->whereNull('deleted_at')
            ->get(['id', 'status']);

        foreach ($agreements as $agreement) {
            DB::transaction(fn () => $this->merge($agreement->id, $agreement->status === 'active'));
        }
    }

    public function down(): void
    {
        //
    }

    /**
     * Fold one agreement's Objective and Scope phases into one.
     */
    private function merge(int $agreementId, bool $isActive): void
    {
        $phases = DB::table('agreement_milestones')
            ->where('agreement_id', $agreementId)
            ->orderBy('position')
            ->get();

        if ($phases->count() !== 3 || $phases->slice(0, 2)->pluck('title')->all() !== ['Objective', 'Scope']) {
            return;
        }

        [$objective, $scope, $turnover] = $phases->all();
        $now = now();

        if ($isActive) {
            $this->mergeTasks($agreementId, $objective->id, $scope->id, $now);
        }

        /* Empty now: everything it held has moved to the first phase. */
        DB::table('agreement_milestones')->where('id', $scope->id)->delete();

        DB::table('agreement_milestones')->where('id', $objective->id)->update([
            'title' => 'Objective & Scope',
            'starts_on' => null,
            'ends_on' => null,
            'planned_starts_on' => null,
            'planned_ends_on' => null,
            'updated_at' => $now,
        ]);

        DB::table('agreement_milestones')->where('id', $turnover->id)->update(['position' => 2]);

        if ($isActive) {
            $this->syncStatus($objective, $now);
        }
    }

    /**
     * Merge each service's Objective and Scope tasks, then move the rest across.
     */
    private function mergeTasks(int $agreementId, int $objectivePhaseId, int $scopePhaseId, mixed $now): void
    {
        $services = DB::table('agreement_requirements')
            ->where('agreement_id', $agreementId)
            ->where('section', 'services')
            ->orderBy('id')
            ->get(['title', 'body']);

        $objectiveTasks = DB::table('agreement_tasks')
            ->where('agreement_milestone_id', $objectivePhaseId)
            ->orderBy('position')
            ->get();

        /** @var Collection<int, object> $scopeTasks */
        $scopeTasks = DB::table('agreement_tasks')
            ->where('agreement_milestone_id', $scopePhaseId)
            ->orderBy('position')
            ->get()
            ->keyBy('id');

        foreach ($objectiveTasks as $task) {
            $service = $services->first(fn ($service) => $service->title === $task->title);

            if ($service === null) {
                continue;
            }

            if (blank($task->description)) {
                DB::table('agreement_tasks')->where('id', $task->id)->update(['description' => $service->body]);
            }

            $partner = $scopeTasks->first(fn ($scopeTask) => $scopeTask->title === $service->body);

            if ($partner === null) {
                continue;
            }

            if ($this->hasSubmission($partner) && $this->hasSubmission($task)) {
                /* Both handed over: keep the Scope task as an item of its own. */
                continue;
            }

            if ($this->hasSubmission($partner)) {
                DB::table('agreement_tasks')->where('id', $task->id)->update(
                    collect(self::SUBMISSION)->mapWithKeys(fn (string $column) => [$column => $partner->{$column}])->all(),
                );
            }

            DB::table('agreement_tasks')->where('id', $partner->id)->delete();
            $scopeTasks->forget($partner->id);
        }

        $position = (int) DB::table('agreement_tasks')->where('agreement_milestone_id', $objectivePhaseId)->max('position');

        foreach ($scopeTasks as $task) {
            DB::table('agreement_tasks')->where('id', $task->id)->update([
                'agreement_milestone_id' => $objectivePhaseId,
                'position' => ++$position,
            ]);
        }

        /* Objective & Scope carries no deadlines, so nothing can be asked to move. */
        $taskIds = DB::table('agreement_tasks')->where('agreement_milestone_id', $objectivePhaseId)->pluck('id');

        DB::table('agreement_tasks')->whereIn('id', $taskIds)->update(['due_on' => null, 'updated_at' => $now]);
        DB::table('deadline_change_requests')
            ->whereIn('agreement_task_id', $taskIds)
            ->where('status', 'pending')
            ->update(['status' => 'withdrawn', 'updated_at' => $now]);
    }

    /**
     * Whether the student has handed anything over on this task.
     */
    private function hasSubmission(object $task): bool
    {
        return $task->status !== 'open'
            || $task->proof_path !== null
            || $task->proof_note !== null
            || $task->proof_url !== null
            || $task->review_note !== null;
    }

    /**
     * The merged phase's status from its tasks, as SyncPhaseStatus derives it.
     */
    private function syncStatus(object $phase, mixed $now): void
    {
        $counts = DB::table('agreement_tasks')
            ->where('agreement_milestone_id', $phase->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $verified = (int) ($counts['verified'] ?? 0);
        $submitted = (int) ($counts['submitted'] ?? 0);
        $open = (int) ($counts['open'] ?? 0);

        $status = match (true) {
            $verified + $submitted + $open === 0 => 'pending',
            $open === 0 && $submitted === 0 => 'approved',
            $open === 0 => 'submitted',
            $verified > 0 || $submitted > 0 => 'in_progress',
            default => 'pending',
        };

        DB::table('agreement_milestones')->where('id', $phase->id)->update([
            'status' => $status,
            'approved_at' => $status === 'approved' ? ($phase->approved_at ?? $now) : null,
            'approved_by' => $status === 'approved' ? $phase->approved_by : null,
        ]);
    }
};
