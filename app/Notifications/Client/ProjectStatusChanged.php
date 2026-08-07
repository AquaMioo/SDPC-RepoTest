<?php

namespace App\Notifications\Client;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the client team when a posting moves between lifecycle states —
 * approved, started, completed or archived.
 */
class ProjectStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Project $project,
        public ProjectStatus $previousStatus,
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
            ->subject(__(':project is now :status', [
                'project' => $this->project->title,
                'status' => strtolower($this->project->status->label()),
            ]))
            ->line($this->summary())
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
            'type' => 'project.status_changed',
            'project_id' => $this->project->id,
            'project_slug' => $this->project->slug,
            'project_title' => $this->project->title,
            'previous_status' => $this->previousStatus->value,
            'status' => $this->project->status->value,
            'summary' => $this->summary(),
        ];
    }

    /**
     * Describe the transition in the client's terms.
     */
    protected function summary(): string
    {
        return match ($this->project->status) {
            ProjectStatus::Open => __('Your posting was approved and is now visible to students.'),
            ProjectStatus::InProgress => __('Your project team is complete and work has started.'),
            ProjectStatus::Completed => __('This project has been marked as delivered.'),
            ProjectStatus::Archived => __('This posting has been archived and is no longer listed.'),
            ProjectStatus::Closed => __('This posting has been closed.'),
            default => __('The status of this posting changed from :from to :to.', [
                'from' => strtolower($this->previousStatus->label()),
                'to' => strtolower($this->project->status->label()),
            ]),
        };
    }
}
