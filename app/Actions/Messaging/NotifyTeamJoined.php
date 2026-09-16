<?php

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\Messaging\TeamJoinedConversation;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells both sides of a thread that a student brought their team into it.
 *
 * The business learns its one-to-one conversation is now a group; the
 * student's teammates learn there is a thread they can now read. The student
 * who pressed the button is told by the toast instead.
 *
 * Failure is logged and swallowed, like NotifyOfMessage: the group has already
 * formed, and a notification that cannot be written must not undo that.
 */
class NotifyTeamJoined
{
    public function handle(Conversation $conversation, User $student): void
    {
        try {
            $conversation->project->team->members()->get()
                ->reject(fn (User $user): bool => $user->is($student))
                ->each(fn (User $user) => $user->notify(new TeamJoinedConversation(
                    $conversation,
                    $student,
                    TeamJoinedConversation::AUDIENCE_CLIENT,
                )));

            $conversation->studentTeam?->members()->get()
                ->reject(fn (User $user): bool => $user->is($student))
                ->each(fn (User $user) => $user->notify(new TeamJoinedConversation(
                    $conversation,
                    $student,
                    TeamJoinedConversation::AUDIENCE_TEAM,
                )));
        } catch (Throwable $exception) {
            Log::warning('A team joined a thread but the participants could not be notified.', [
                'conversation_id' => $conversation->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
