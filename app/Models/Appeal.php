<?php

namespace App\Models;

use App\Enums\AppealStatus;
use App\Enums\UserStatus;
use Database\Factories\AppealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One account's answer to a decision taken about it.
 *
 * @property int $id
 * @property int $user_id
 * @property UserStatus $account_status
 * @property string $body
 * @property AppealStatus $status
 * @property string|null $decision_note
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read User|null $reviewer
 */
#[Fillable(['user_id', 'account_status', 'body', 'status'])]
class Appeal extends Model
{
    /** @use HasFactory<AppealFactory> */
    use HasFactory;

    /**
     * The account that filed the appeal.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The administrator who decided it, if one has.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope to appeals still waiting on an administrator.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', AppealStatus::Pending);
    }

    /**
     * Whether the appeal has been decided.
     */
    public function isDecided(): bool
    {
        return ! $this->status->isOpen();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_status' => UserStatus::class,
            'status' => AppealStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
