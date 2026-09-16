<?php

namespace App\Notifications\Messaging;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * A student brought their team into a conversation.
 *
 * Sent to both sides of the thread that did not press the button: the
 * business, whose one-to-one conversation just became a group, and the
 * student's teammates, who can now read and write a thread they have never
 * seen. The payload says which of the two it is for, so the bell can word it
 * for the reader.
 *
 * Deliberately NOT ShouldQueue, like Messaging\NewMessage: the queue worker is
 * not reliably running, and a group forming silently is exactly what this is
 * meant to prevent.
 */
class TeamJoinedConversation extends Notification
{
    /** The business side of the thread. */
    public const AUDIENCE_CLIENT = 'client';

    /** The student's teammates. */
    public const AUDIENCE_TEAM = 'team';

    public function __construct(
        public readonly Conversation $conversation,
        public readonly User $student,
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
            'type' => 'conversation.team_joined',
            'audience' => $this->audience,
            'conversation_id' => $this->conversation->id,
            'student_name' => $this->student->name,
            'team_name' => $this->conversation->studentTeam?->name,
            'project_title' => $project->title,
            'business_name' => $project->team->clientProfile?->business_name ?? $project->team->name,
        ];
    }
}
