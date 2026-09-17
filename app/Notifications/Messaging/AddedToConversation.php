<?php

namespace App\Notifications\Messaging;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * The team's creator invited a teammate into a group chat.
 *
 * Sent to the teammate, who can now read a thread they have never seen, and
 * to the business, whose conversation just gained a person. The payload says
 * which of the two it is for, so the bell can word it for the reader.
 *
 * Deliberately NOT ShouldQueue, like Messaging\NewMessage: there is no queue
 * worker on the live site, and a teammate who is never told they were added
 * does not know the chat exists.
 */
class AddedToConversation extends Notification
{
    /** The teammate who was invited. */
    public const AUDIENCE_MEMBER = 'member';

    /** The business side of the thread. */
    public const AUDIENCE_CLIENT = 'client';

    public function __construct(
        public readonly Conversation $conversation,
        public readonly User $member,
        public readonly User $inviter,
        public readonly string $audience,
    ) {}

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
        $project = $this->conversation->project;

        return [
            'type' => 'conversation.member_added',
            'audience' => $this->audience,
            'conversation_id' => $this->conversation->id,
            'inviter_name' => $this->inviter->name,
            'member_name' => $this->member->name,
            'team_name' => $this->conversation->studentTeam?->name,
            'project_title' => $project->title,
            'business_name' => $project->team->clientProfile?->business_name ?? $project->team->name,
        ];
    }
}
