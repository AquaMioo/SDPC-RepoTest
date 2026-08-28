<?php

namespace App\Notifications\Messaging;

use App\Models\Message;
use Illuminate\Notifications\Notification;

/**
 * Tells somebody a thread they are on has woken up.
 *
 * Deliberately NOT ShouldQueue, for the same reason MessageSent is
 * ShouldBroadcastNow: a thread that only tells you about a message once a
 * worker gets round to it is worse than one that costs a row on the request.
 * Writing this row is one insert into the database the message was just
 * written to.
 *
 * Database only, no mail. The thread updates live over Reverb and the inbox
 * carries its own unread count, so the bell is the part that is missing when
 * you are somewhere else in the app — an email for every conversation that
 * wakes up would be noise, and there is no presence signal here good enough
 * to decide who is genuinely away.
 *
 * Raised once per wake, not once per message: NotifyOfMessage only sends when
 * the thread was fully read by the recipient beforehand, so a burst of ten
 * lines is one notification rather than ten.
 */
class NewMessage extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Message $message) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $conversation = $this->message->conversation;

        return [
            'type' => 'message.received',
            'conversation_id' => $conversation->id,
            'message_id' => $this->message->id,
            'sender_name' => $this->message->sender->name,
            'project_title' => $conversation->project->title,
            'preview' => $this->preview(),
        ];
    }

    /**
     * A short, safe stand-in for the message body.
     *
     * A message can be a picture on its own, so an empty body is not an error
     * — it is a message with nothing to quote.
     */
    protected function preview(): ?string
    {
        $body = trim((string) $this->message->body);

        if ($body === '') {
            return $this->message->attachment_path === null ? null : __('Sent a picture');
        }

        return str($body)->limit(120)->toString();
    }
}
