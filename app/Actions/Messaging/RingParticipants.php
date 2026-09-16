<?php

namespace App\Actions\Messaging;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Messaging\CallEnded;
use App\Notifications\Messaging\IncomingCall;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Rings everyone else in a conversation when a call starts, and stops the
 * ringing when it ends.
 *
 * Same bargain as AnnounceMeeting: the meeting row is already committed, so a
 * broadcaster that is down is logged, never turned into an error for the
 * person placing or ending the call.
 */
class RingParticipants
{
    /**
     * Ring everyone in the thread except the caller.
     */
    public function ring(Meeting $meeting, User $caller): void
    {
        $this->send($meeting, $caller, new IncomingCall($meeting, $caller));
    }

    /**
     * Stop the ringing on everyone else's screen.
     */
    public function hangUp(Meeting $meeting, User $by): void
    {
        $this->send($meeting, $by, new CallEnded($meeting));
    }

    /**
     * One recipient at a time, so a broadcast failing for one person does not
     * leave the rest of the thread un-rung.
     */
    protected function send(Meeting $meeting, User $except, object $notification): void
    {
        $recipients = $meeting->conversation
            ->participants()
            ->reject(fn (User $user): bool => $user->is($except));

        foreach ($recipients as $recipient) {
            try {
                Notification::send($recipient, $notification);
            } catch (Throwable $exception) {
                Log::warning('A call notification could not be delivered.', [
                    'meeting_id' => $meeting->id,
                    'recipient_id' => $recipient->id,
                    'notification' => $notification::class,
                    'reason' => $exception->getMessage(),
                ]);
            }
        }
    }
}
