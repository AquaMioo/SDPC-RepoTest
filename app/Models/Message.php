<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|null $user_id
 * @property string|null $body
 * @property Carbon|null $edited_at
 * @property Carbon|null $removed_at
 * @property string|null $attachment_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read User|null $sender
 * @property-read Collection<int, MessageReaction> $reactions
 */
#[Fillable(['conversation_id', 'user_id', 'body', 'attachment_path'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * The reactions left on this message.
     *
     * @return HasMany<MessageReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * How long after sending a message may still be reworded.
     *
     * Short on purpose. Editing exists to fix a typo you spot as you hit send,
     * not to change what you agreed to an hour ago — this thread is the record
     * of a working arrangement, and a message that can be rewritten long after
     * the other side read it is not a record.
     */
    public const EDIT_WINDOW_SECONDS = 30;

    /**
     * Whether the sender has taken this message back.
     */
    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    /**
     * Whether the edit window is still open.
     */
    public function isWithinEditWindow(): bool
    {
        return $this->created_at !== null
            && $this->created_at->diffInSeconds(now()) < self::EDIT_WINDOW_SECONDS;
    }

    /**
     * The moment editing closes, as epoch milliseconds.
     *
     * Sent to the client so the button can disappear on its own clock rather
     * than waiting for the next reload to be told it is too late.
     */
    public function editableUntilMs(): ?int
    {
        return $this->created_at
            ?->addSeconds(self::EDIT_WINDOW_SECONDS)
            ->getTimestampMs();
    }

    /**
     * Whether the sender has changed it since sending.
     */
    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /**
     * Whether this account is the one that wrote it.
     *
     * Editing and removing are the sender's alone — the other side of a
     * conversation must never be able to rewrite what was said to them.
     */
    public function wasSentBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * The reactions grouped for display: which emoji, how many, and whether
     * this viewer is one of them.
     *
     * @return list<array{emoji: string, count: int, reacted: bool}>
     */
    public function reactionSummary(User $viewer): array
    {
        return $this->reactions
            ->groupBy('emoji')
            ->map(fn (Collection $rows, string $emoji): array => [
                'emoji' => $emoji,
                'count' => $rows->count(),
                'reacted' => $rows->contains('user_id', $viewer->id),
            ])
            ->values()
            ->all();
    }

    /**
     * Get the thread the message belongs to.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get whoever wrote it, if they still have an account.
     *
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        /* Named for the role it plays, so the column has to be spelled out. */
        return $this->belongsTo(User::class, 'user_id');
    }
}
