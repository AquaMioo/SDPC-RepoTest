<?php

namespace App\Events;

use App\Models\Meeting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Somebody opened a video meeting on a thread.
 *
 * This is the invitation. It rides the conversation's existing private
 * channel rather than a channel of its own: whoever may read the thread may
 * be called on it, and adding a second channel would mean a second
 * authorisation rule that could drift from the first.
 *
 * ShouldBroadcastNow for the same reason MessageSent is — an invitation that
 * waits on a queue worker is not an invitation. App\Actions\Messaging\
 * AnnounceMeeting catches whatever this throws, so a broadcaster being down
 * cannot fail the call for the person placing it.
 */
class MeetingStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Meeting $meeting) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversations.'.$this->meeting->conversation_id)];
    }

    /**
     * The name the client listens for.
     */
    public function broadcastAs(): string
    {
        return 'meeting.started';
    }

    /**
     * Deliberately no token: this says a call is happening, not how to join
     * it. The other side asks for its own token over HTTP, where the
     * participant check runs again against the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->meeting->id,
            'conversationId' => $this->meeting->conversation_id,
            'createdBy' => $this->meeting->created_by,
            'startedAt' => $this->meeting->started_at?->toIso8601String(),
        ];
    }
}
