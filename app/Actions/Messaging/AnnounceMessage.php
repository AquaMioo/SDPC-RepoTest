<?php

namespace App\Actions\Messaging;

use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the other side of a thread that something changed, without ever
 * being able to fail the thing that changed it.
 *
 * MessageSent is ShouldBroadcastNow, which means it goes out on the request
 * rather than through a worker. That is deliberate — a chat that stops
 * updating whenever a queue worker is not running is worse than one that
 * costs a millisecond inline. But it has a consequence that bit us: Reverb is
 * a separate process someone has to start, and when it is not listening the
 * broadcast throws a BroadcastException. The message was already committed by
 * then, so the sender got a 500 for a message that had in fact been sent.
 *
 * So the live update is treated as what it is: a courtesy on top of a write
 * that already succeeded. If it cannot be delivered the failure is logged and
 * the request carries on, and the thread's 30-second poll backstop picks the
 * message up instead. Same reasoning as SheerIdStudentVerifier — a service
 * being down must never stop somebody using the platform.
 */
class AnnounceMessage
{
    /**
     * Broadcast the change, swallowing any failure to do so.
     *
     * Throwable rather than BroadcastException on purpose. Every driver wraps
     * transport failures differently, and there is no failure mode here worth
     * turning a delivered message into an error page — the worst case is a
     * participant seeing the line up to thirty seconds late.
     */
    public function handle(Message $message): void
    {
        try {
            MessageSent::dispatch($message);
        } catch (Throwable $exception) {
            Log::warning('A message was saved but could not be broadcast live.', [
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
