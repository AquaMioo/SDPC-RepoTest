<?php

namespace App\Models;

use App\Enums\AgreementParty;
use App\Enums\ContractRequestStatus;
use App\Enums\ContractRequestType;
use Database\Factories\ContractRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One side asking the other to end a contract: a denial of an unsigned MoA
 * or Addendum, or a termination of a running collaboration (owner,
 * 2026-10-18). Nothing happens until the other side accepts it.
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property ContractRequestType $type
 * @property AgreementParty $party
 * @property int|null $requested_by
 * @property string $reason
 * @property int|null $rating
 * @property string|null $feedback
 * @property ContractRequestStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agreement|Addendum $subject
 * @property-read User|null $requester
 * @property-read User|null $decider
 */
#[Fillable([
    'subject_type', 'subject_id', 'type', 'party', 'requested_by', 'reason', 'rating',
    'feedback', 'status', 'decided_by', 'decided_at', 'decision_note',
])]
class ContractRequest extends Model
{
    /** @use HasFactory<ContractRequestFactory> */
    use HasFactory;

    /**
     * Get the contract the request is about: an Agreement or an Addendum.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the person who asked.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the person who answered.
     *
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * The agreement the request belongs to, directly or through its addendum.
     */
    public function agreement(): Agreement
    {
        $subject = $this->subject;

        return $subject instanceof Addendum ? $subject->agreement : $subject;
    }

    /**
     * Scope to the requests still waiting for an answer.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', ContractRequestStatus::Pending);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContractRequestType::class,
            'party' => AgreementParty::class,
            'status' => ContractRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }
}
