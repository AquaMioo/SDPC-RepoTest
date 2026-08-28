<?php

namespace App\Notifications\Client;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the student when a client invites them onto a posting.
 *
 * The mirror of ApplicationReceived: that one tells a client somebody applied,
 * this one tells a student somebody asked for them. Inviting used to be silent
 * — the application row and the conversation were both created and the student
 * was told neither — so an invitation only surfaced if they happened to open
 * their workflow.
 */
class ProjectInvitation extends Notification implements ShouldQueue
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
            ->subject(__('You were invited to :project', ['project' => $project->title]))
            ->line(__(':business invited you to work on :project.', [
                'business' => $this->business(),
                'project' => $project->title,
            ]))
            ->line(__('Open your workflow to look at the brief and reply.'))
            ->action(__('View the invitation'), route('student.workflow', [
                'current_team' => $notifiable->currentTeam->slug,
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
            'type' => 'project.invitation',
            'application_id' => $this->application->id,
            'project_id' => $this->application->project_id,
            'project_slug' => $this->application->project->slug,
            'project_title' => $this->application->project->title,
            'client_name' => $this->business(),
        ];
    }

    /**
     * What the client is called on screen.
     */
    protected function business(): string
    {
        $team = $this->application->project->team;

        return $team->clientProfile?->business_name ?? $team->name;
    }
}
