<?php

namespace App\Notifications\Client;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the client team when a posting is submitted for admin screening.
 */
class ProjectPublished extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Project $project) {}

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
            ->subject(__(':project is awaiting review', ['project' => $this->project->title]))
            ->line(__('Your posting has been submitted and is waiting on an administrator to screen it.'))
            ->line(__('Students will see it, and matching will run, as soon as it is approved.'))
            ->action(__('View posting'), route('projects.show', [
                'current_team' => $this->project->team->slug,
                'project' => $this->project->slug,
            ]));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'project.published',
            'project_id' => $this->project->id,
            'project_slug' => $this->project->slug,
            'project_title' => $this->project->title,
        ];
    }
}
