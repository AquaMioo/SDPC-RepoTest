<?php

namespace App\Notifications\Client;

use App\Models\Application;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the business when a student takes an invitation up.
 *
 * The mirror of StudentAccepted. That one tells a student a client said yes;
 * this one tells a client a student did — the same event from the other end of
 * an invitation, where the decision was never the client's to make.
 *
 * Deliberately NOT ShouldQueue. This is the answer to a question the client
 * asked and is waiting on, and there is no queue worker on this deployment —
 * an acceptance nobody hears about is the whole reason invitations felt broken
 * in the first place.
 */
class InvitationAccepted extends Notification
{
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
            ->subject(__(':student accepted your invitation', [
                'student' => $this->application->student->name,
            ]))
            ->line(__(':student is joining :project.', [
                'student' => $this->application->student->name,
                'project' => $project->title,
            ]))
            ->line(__('An agreement has been drafted. The work starts once both sides have signed it.'))
            ->action(__('Open the agreement'), route('agreements.index', [
                'current_team' => $project->team->slug,
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
            'type' => 'invitation.accepted',
            'application_id' => $this->application->id,
            'project_id' => $this->application->project_id,
            'project_slug' => $this->application->project->slug,
            'project_title' => $this->application->project->title,
            'student_id' => $this->application->user_id,
            'student_name' => $this->application->student->name,
        ];
    }
}
