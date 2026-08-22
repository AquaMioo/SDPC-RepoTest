<?php

use App\Broadcasting\ConversationChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * One private channel per thread.
 *
 * The same participation check the HTTP routes make, because a socket is just
 * another door into the same conversation: whoever may read a thread over a
 * request may listen to it over a connection, and nobody else may do either.
 */
Broadcast::channel('conversations.{conversationId}', ConversationChannel::class);
