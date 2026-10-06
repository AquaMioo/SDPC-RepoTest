<?php

namespace App\Notifications\Client;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the client team when an administrator removes their posting from
 * the review queue, with the administrator's reason.
 *
 * Carries the title as text rather than the Project: the posting is deleted
 * straight after this is sent, and a queued notification holding the model
 * could not load it back.
 */
class PostingRemoved extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $projectTitle,
        public string $reason,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':project was removed', ['project' => $this->projectTitle]))
            ->line(__('An administrator removed your posting ":project".', ['project' => $this->projectTitle]))
            ->line(__('Reason: :reason', ['reason' => $this->reason]));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'project.removed',
            'project_title' => $this->projectTitle,
            'reason' => $this->reason,
        ];
    }
}
