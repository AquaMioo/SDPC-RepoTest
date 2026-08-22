<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A message arrived in a thread.
 *
 * Deliberately thin: it carries the thread id and the message id, not the
 * words. The listening client reloads the thread through the same controller
 * that would have served a full page, so what a participant may see is decided
 * in one place — and a socket frame never becomes a way to read something a
 * request would have refused.
 *
 * ShouldBroadcastNow, not ShouldBroadcast: the queue runs on the database
 * connection, so a queued broadcast waits on a worker process. A chat that
 * silently stops updating whenever that worker is not running is a far worse
 * trade than the millisecond broadcasting inline costs the request.
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversations.'.$this->message->conversation_id)];
    }

    /**
     * The name the client listens for.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return [
            'conversationId' => $this->message->conversation_id,
            'messageId' => $this->message->id,
        ];
    }
}
