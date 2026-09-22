<?php

namespace App\Notifications\Agreements;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the student side when the client completes the project.
 *
 * The signer and every teammate: completing is what frees them to take the
 * next project, and a release nobody hears about looks like a lock that never
 * lifted.
 */
class ProjectCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Agreement $agreement) {}

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
            ->subject(__(':project is complete', ['project' => $this->agreement->project->title]))
            ->line(__(':business accepted the turnover and marked :project as complete.', [
                'business' => $this->businessName(),
                'project' => $this->agreement->project->title,
            ]))
            ->line(__('You are free to apply for your next project.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'project.completed',
            'agreement_id' => $this->agreement->id,
            'agreement_reference' => $this->agreement->reference,
            'project_id' => $this->agreement->project_id,
            'project_title' => $this->agreement->project->title,
            'business_name' => $this->businessName(),
        ];
    }

    /**
     * The business the student worked for, by the name it trades under.
     */
    protected function businessName(): string
    {
        $team = $this->agreement->project->team;

        return $team->clientProfile?->business_name ?? $team->name;
    }
}
