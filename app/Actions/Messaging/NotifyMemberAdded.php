<?php

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\Messaging\AddedToConversation;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells a teammate they were invited into a group chat, and tells the
 * business who just joined its conversation.
 *
 * The creator who pressed Invite is told by the toast instead.
 *
 * Failure is logged and swallowed, like NotifyOfMessage: the teammate is
 * already in, and a notification that cannot be written must not undo that.
 */
class NotifyMemberAdded
{
    public function handle(Conversation $conversation, User $member, User $inviter): void
    {
        try {
            $member->notify(new AddedToConversation(
                $conversation,
                $member,
                $inviter,
                AddedToConversation::AUDIENCE_MEMBER,
            ));

            $conversation->project->team->members()->get()
                ->each(fn (User $client) => $client->notify(new AddedToConversation(
                    $conversation,
                    $member,
                    $inviter,
                    AddedToConversation::AUDIENCE_CLIENT,
                )));
        } catch (Throwable $exception) {
            Log::warning('A teammate was added to a group chat but could not be notified.', [
                'conversation_id' => $conversation->id,
                'member_id' => $member->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
