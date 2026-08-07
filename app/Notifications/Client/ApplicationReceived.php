<?php

namespace App\Notifications\Client;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the client team when a student applies to one of their postings.
 */
class ApplicationReceived extends Notification implements ShouldQueue
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
            ->subject(__('New applicant for :project', ['project' => $project->title]))
            ->line(__(':student applied to :project.', [
                'student' => $this->application->student->name,
                'project' => $project->title,
            ]))
            ->action(__('Review applicants'), route('projects.applicants.index', [
                'current_team' => $project->team->slug,
                'project' => $project->slug,
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
            'type' => 'application.received',
            'application_id' => $this->application->id,
            'project_id' => $this->application->project_id,
            'project_slug' => $this->application->project->slug,
            'project_title' => $this->application->project->title,
            'student_id' => $this->application->user_id,
            'student_name' => $this->application->student->name,
        ];
    }
}
