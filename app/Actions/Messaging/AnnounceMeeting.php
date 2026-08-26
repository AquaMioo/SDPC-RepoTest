<?php

namespace App\Actions\Messaging;

use App\Events\MeetingStarted;
use App\Models\Meeting;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the other side of a thread that a call has started, without ever
 * being able to fail the call.
 *
 * The same bargain AnnounceMessage strikes, and for the same reason: the
 * broadcast happens inline on the request, so a broadcaster that is not
 * listening throws after the meeting row is already committed. The person who
 * pressed "start a call" would get an error page for a call that is in fact
 * running and which they can join.
 *
 * So a failed invitation is logged and the request carries on. The caller is
 * in the meeting either way; the worst case is the other side having to open
 * the thread to notice, which is where they already were.
 */
class AnnounceMeeting
{
    public function handle(Meeting $meeting): void
    {
        try {
            MeetingStarted::dispatch($meeting);
        } catch (Throwable $exception) {
            Log::warning('A meeting was opened but its invitation could not be broadcast.', [
                'conversation_id' => $meeting->conversation_id,
                'meeting_id' => $meeting->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
