<?php

namespace App\Models;

use App\Enums\MilestoneStatus;
use Database\Factories\AgreementMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One agreed piece of work: what it is, what it costs, when it runs.
 *
 * The platform's only real source of schedule and progress. The dashboard
 * calendar marks these dates, the progress ring averages these statuses, and
 * the Project process screen draws a bar per milestone.
 *
 * @property int $id
 * @property int $agreement_id
 * @property int $position
 * @property string $title
 * @property string|null $description
 * @property int $amount
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property MilestoneStatus $status
 * @property string|null $review_note
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agreement $agreement
 * @property-read User|null $approver
 */
#[Fillable([
    'agreement_id', 'position', 'title', 'description', 'amount', 'starts_on',
    'ends_on', 'status', 'review_note', 'submitted_at', 'approved_at', 'approved_by',
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
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
