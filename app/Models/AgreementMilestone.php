<?php

namespace App\Models;

use App\Enums\MilestoneStatus;
use Carbon\CarbonInterface;
use Database\Factories\AgreementMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One agreed piece of work: what it is, what it costs, when it runs.
 *
 * A phase of the build (Design, Build, Turnover by default). Since Project
 * Management its progress is its checklist: the tasks the client verified
 * over the tasks the student wrote, and its status follows from those — see
 * App\Actions\Agreements\SyncPhaseStatus.
 *
 * @property int $id
 * @property int $agreement_id
 * @property int $position
 * @property string $title
 * @property string|null $description
 * @property int $amount
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $planned_starts_on
 * @property Carbon|null $planned_ends_on
 * @property MilestoneStatus $status
 * @property string|null $review_note
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agreement $agreement
 * @property-read User|null $approver
 * @property-read Collection<int, AgreementTask> $tasks
 */
#[Fillable([
    'agreement_id', 'position', 'title', 'description', 'amount', 'starts_on',
    'ends_on', 'planned_starts_on', 'planned_ends_on', 'status', 'review_note',
    'submitted_at', 'approved_at', 'approved_by',
])]
class AgreementMilestone extends Model
{
    /** @use HasFactory<AgreementMilestoneFactory> */
    use HasFactory;

    /**
     * Get the agreement the milestone belongs to.
     *
     * @return BelongsTo<Agreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * Get the client staff member who signed the work off.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        /* Named for the role it plays, so the column has to be spelled out. */
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the money recorded against this milestone.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the checklist the student keeps for this phase.
     *
     * @return HasMany<AgreementTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(AgreementTask::class)->orderBy('position')->orderBy('id');
    }

    /**
     * When the phase starts on the student's working schedule.
     *
     * The plan when the student has moved it, the agreed date otherwise.
     */
    public function scheduledStartsOn(): ?CarbonInterface
    {
        return $this->planned_starts_on ?? $this->starts_on;
    }

    /**
     * When the phase ends on the student's working schedule.
     */
    public function scheduledEndsOn(): ?CarbonInterface
    {
        return $this->planned_ends_on ?? $this->ends_on;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MilestoneStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'planned_starts_on' => 'date',
            'planned_ends_on' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
