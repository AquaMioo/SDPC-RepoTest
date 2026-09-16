<?php

namespace App\Models;

use Database\Factories\MeetingAttendeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's place in one call.
 *
 * @property int $id
 * @property int $meeting_id
 * @property int $user_id
 * @property Carbon $joined_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $left_at
 * @property-read Meeting $meeting
 * @property-read User $user
 */
#[Fillable(['meeting_id', 'user_id', 'joined_at', 'last_seen_at', 'left_at'])]
class MeetingAttendee extends Model
{
    /** @use HasFactory<MeetingAttendeeFactory> */
    use HasFactory;

    /**
     * Seconds without a heartbeat before somebody counts as gone.
     *
     * The call screen beats every 20 seconds. Several missed beats is a closed
     * tab or a dropped connection rather than a slow request or a phone that
     * briefly switched apps.
     */
    public const PRESENCE_WINDOW = 90;

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * People still in the call: not left, and heard from recently.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function present(Builder $query): void
    {
        $query->whereNull('left_at')
            ->where('last_seen_at', '>=', now()->subSeconds(self::PRESENCE_WINDOW));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }
}
