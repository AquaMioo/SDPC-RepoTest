<?php

namespace App\Broadcasting;

use App\Models\Conversation;
use App\Models\User;

/**
 * Who may listen to one thread's channel.
 *
 * A class rather than a closure so the rule can be called directly in a test.
 * Checking it through the HTTP auth endpoint would prove nothing: the suite
 * runs on the null broadcaster, which answers every authorisation with 200
 * and never consults the rule at all.
 */
class ConversationChannel
{
    /**
     * Authenticate the user's access to the channel.
     *
     * The same participation check the HTTP routes make, because a socket is
     * another door into the same conversation.
     */
    public function join(User $user, int $conversationId): bool
    {
        $conversation = Conversation::find($conversationId);

        return $conversation !== null && $conversation->isParticipant($user);
    }
}
