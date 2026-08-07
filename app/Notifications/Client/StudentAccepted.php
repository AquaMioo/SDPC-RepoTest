<?php

namespace App\Notifications\Client;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the student when a client accepts them onto a project.
 */
class StudentAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Application $application) {}

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
        $project = $this->application->project;

        return (new MailMessage)
            ->subject(__("You're on :project", ['project' => $project->title]))
            ->line(__(':business accepted you onto :project.', [
                'business' => $project->team->clientProfile?->business_name ?? $project->team->name,
                'project' => $project->title,
            ]))
            ->line(__('The client will be in touch with next steps.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application.accepted',
            'application_id' => $this->application->id,
            'project_id' => $this->application->project_id,
            'project_slug' => $this->application->project->slug,
            'project_title' => $this->application->project->title,
        ];
    }
}
