<?php

namespace App\Models;

use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A video meeting held on one project conversation.
 *
 * The row is the record; the Agora channel is not. A channel exists only while
 * somebody is connected to it, so the platform's own view of what happened —
 * who called, when, and whether it ended — lives here or nowhere.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $created_by
 * @property string $channel_name
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 */
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'created_by',
        'channel_name',
        'scheduled_at',
        'started_at',
        'ended_at',
    ];

    /**
     * The thread this call belongs to, which is also what decides who may join.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Whoever started it. Either side of a thread may.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Booked meetings still worth showing on a thread.
     *
     * The hour of grace is deliberate: somebody running late for their own
     * meeting should still find it in the thread rather than watch it vanish
     * at the appointed minute.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->whereNotNull('scheduled_at')
            ->whereNull('started_at')
            ->whereNull('ended_at')
            ->where('scheduled_at', '>=', now()->subHour())
            ->orderBy('scheduled_at');
    }

    /**
     * Booked for later and not yet begun.
     *
     * A meeting stops being scheduled the moment somebody joins it, which is
     * what starting it means — the diary entry becomes a call in progress.
     */
    public function isScheduled(): bool
    {
        return $this->scheduled_at !== null && $this->started_at === null;
    }

    /**
     * Whether somebody may still join.
     *
     * A meeting that was never started is joinable — that is the invitation
     * case, where the row exists so the other side has something to accept.
     */
    public function isJoinable(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * May this user join this call?
     *
     * Delegates to the conversation rather than re-deriving the two sides, so
     * the call and the socket can never disagree about who belongs here.
     * App\Broadcasting\ConversationChannel asks the same question.
     */
    public function isParticipant(User $user): bool
    {
        return $this->conversation !== null
            && $this->conversation->isParticipant($user);
    }

    /**
     * A fresh Agora channel name.
     *
     * Random rather than derived from the conversation, so a token minted for
     * a finished call cannot be replayed into the next one on the same thread.
     */
    public static function newChannelName(): string
    {
        return 'sdpcc-'.Str::lower(Str::random(24));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
