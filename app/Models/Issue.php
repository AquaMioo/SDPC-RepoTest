<?php

namespace App\Models;

use App\Enums\IssueCategory;
use App\Enums\IssueStatus;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One account's report about another.
 *
 * @property int $id
 * @property int $reporter_id
 * @property int $reported_user_id
 * @property IssueCategory $category
 * @property string $description
 * @property IssueStatus $status
 * @property string|null $resolution
 * @property int|null $handled_by
 * @property Carbon|null $handled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $reporter
 * @property-read User $reportedUser
 * @property-read User|null $handler
 */
#[Fillable(['reporter_id', 'reported_user_id', 'category', 'description', 'status'])]
class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => IssueCategory::class,
            'status' => IssueStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * The account that filed the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * The account the report is about.
     *
     * @return BelongsTo<User, $this>
     */
    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    /**
     * The administrator who resolved the report, if one has.
     *
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Scope to reports still waiting on an administrator.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [IssueStatus::Pending, IssueStatus::InReview]);
    }

    /**
     * Whether the report has been decided.
     */
    public function isResolved(): bool
    {
        return $this->status === IssueStatus::Resolved;
    }
}
