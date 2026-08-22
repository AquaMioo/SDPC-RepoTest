<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One account's reaction to one message.
 *
 * @property int $id
 * @property int $message_id
 * @property int $user_id
 * @property string $emoji
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Message $message
 * @property-read User $user
 */
#[Fillable(['message_id', 'user_id', 'emoji'])]
class MessageReaction extends Model
{
    /**
     * The reactions a message may carry.
     *
     * A closed set, so the column stays short and the picker stays a row of
     * buttons rather than a search field over every emoji there is.
     *
     * @var list<string>
     */
    public const ALLOWED = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    /**
     * The message reacted to.
     *
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * The account that reacted.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
