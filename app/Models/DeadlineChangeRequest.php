<?php

namespace App\Models;

use App\Enums\DeadlineRequestStatus;
use Database\Factories\DeadlineChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student side's ask to move a deadline, and the client's answer.
 *
 * About a task's deadline (agreement_task_id) or the final deadline — the end
 * of the Turnover phase (agreement_milestone_id). The date itself only moves
 * when the client approves; see App\Actions\Agreements\DecideDeadlineChange.
 *
 * @property int $id
 * @property int $agreement_id
 * @property int|null $agreement_task_id
 * @property int|null $agreement_milestone_id
 * @property int|null $requested_by
 * @property Carbon|null $previous_on
 * @property Carbon $proposed_on
 * @property string|null $reason
 * @property DeadlineRequestStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agreement $agreement
 * @property-read AgreementTask|null $task
 * @property-read AgreementMilestone|null $milestone
 * @property-read User|null $requester
 * @property-read User|null $decider
 */
#[Fillable([
    'agreement_id', 'agreement_task_id', 'agreement_milestone_id', 'requested_by',
    'previous_on', 'proposed_on', 'reason', 'status', 'decided_by', 'decided_at',
    'decision_note',
])]
class DeadlineChangeRequest extends Model
{
    /** @use HasFactory<DeadlineChangeRequestFactory> */
    use HasFactory;

    /**
     * Get the agreement the deadline belongs to.
     *
     * @return BelongsTo<Agreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * Get the task whose deadline would move, when it is a task's.
     *
     * @return BelongsTo<AgreementTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(AgreementTask::class, 'agreement_task_id');
    }

    /**
     * Get the Turnover phase, when it is the final deadline that would move.
     *
     * @return BelongsTo<AgreementMilestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(AgreementMilestone::class, 'agreement_milestone_id');
    }

    /**
     * Get the student who asked.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the client team member who decided.
     *
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Determine if the ask is about the final deadline rather than a task's.
     */
    public function isForFinalDeadline(): bool
    {
        return $this->agreement_milestone_id !== null;
    }

    /**
     * Scope to the asks still waiting on the client.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', DeadlineRequestStatus::Pending);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeadlineRequestStatus::class,
            'previous_on' => 'date',
            'proposed_on' => 'date',
            'decided_at' => 'datetime',
        ];
    }
}
