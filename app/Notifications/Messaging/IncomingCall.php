<?php

namespace App\Notifications\Messaging;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Somebody started a call in a conversation you are part of.
 *
 * The phone ringing. MeetingStarted already reaches whoever has that thread
 * open; this reaches everyone else in it wherever they are on the platform, on
 * their own private channel, and the browser rings until they answer, decline
 * or the call is given up on (see CallEnded).
 *
 * Broadcast on the sync connection and never queued: a ring that arrives when
 * a worker gets round to it is a missed call. The database row stays behind
 * in the bell as the record of the call.
 */
class IncomingCall extends Notification
{
    public function __construct(
        public readonly Meeting $meeting,
        public readonly User $caller,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
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
        return 'call.incoming';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $conversation = $this->meeting->conversation;

        return [
            'type' => 'call.incoming',
            'meeting_id' => $this->meeting->id,
            'conversation_id' => $conversation->id,
            'caller_name' => $this->caller->name,
            'project_title' => $conversation->project->title,
            'started_at' => $this->meeting->started_at?->toIso8601String(),
        ];
    }
}
