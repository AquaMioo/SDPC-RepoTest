<?php

namespace App\Events;

use App\Models\Meeting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Somebody booked a call on a thread for later.
 *
 * Distinct from MeetingStarted because the two ask different things of the
 * person receiving them: one is a phone ringing, the other is a note in a
 * diary. A single event with a nullable time would have the client guessing
 * which of those it was holding.
 *
 * Rides the conversation's own private channel for the same reason
 * MeetingStarted does — whoever may read the thread may be invited on it — and
 * carries no token, because a scheduled meeting has nothing to join yet.
 */
class MeetingScheduled implements ShouldBroadcastNow
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

    public function broadcastAs(): string
    {
        return 'meeting.scheduled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->meeting->id,
            'conversationId' => $this->meeting->conversation_id,
            'createdBy' => $this->meeting->created_by,
            'scheduledAt' => $this->meeting->scheduled_at?->toIso8601String(),
        ];
    }
}
