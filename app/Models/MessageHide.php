<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One account hiding one message from its own view.
 *
 * The "remove for you" half of removal. messages.removed_at is the other half,
 * and the two must not be confused: this row changes nothing for anybody else
 * in the thread.
 *
 * @property int $id
 * @property int $message_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Message $message
 * @property-read User $user
 */
#[Fillable(['message_id', 'user_id'])]
class MessageHide extends Model
{
    /**
     * The message being hidden.
     *
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * The account hiding it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
