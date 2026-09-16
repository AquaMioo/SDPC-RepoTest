<?php

namespace App\Notifications\Messaging;

use App\Models\Meeting;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * A call ended — stop ringing for it.
 *
 * Broadcast only. There is nothing to keep: the IncomingCall row already
 * records the call, and this exists so that a phone ringing on somebody's
 * screen stops when the caller hangs up, rather than inviting them into a
 * meeting that is over.
 */
class CallEnded extends Notification
{
    public function __construct(public readonly Meeting $meeting) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }

    /**
     * The name the browser listens for.
     */
    public function broadcastType(): string
    {
        return 'call.ended';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'meeting_id' => $this->meeting->id,
            'conversation_id' => $this->meeting->conversation_id,
        ];
    }
}
