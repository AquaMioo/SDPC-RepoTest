<?php

namespace App\Notifications\Client;

use App\Enums\ApplicationSource;
use App\Models\Application;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a business whose invitation was closed because the student it
 * invited was taken on by another client.
 *
 * Without it the business kept waiting on an answer that could not come: a
 * student holds one project at a time, so the other invitations were dead the
 * moment one was accepted. See App\Actions\Student\CloseOtherInvitations.
 *
 * Deliberately NOT ShouldQueue, like InvitationAccepted: there is no queue
 * worker on this deployment, and this is the answer to a question the business
 * is waiting on.
 */
class InvitationClosed extends Notification
{
    /**
     * @param  Application  $invitation  the business's invitation, now closed
     * @param  Application  $taken  the one the student was taken on through
     */
    public function __construct(
        public Application $invitation,
        public Application $taken,
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
        $project = $this->invitation->project;

        return (new MailMessage)
            ->subject(__(':student is no longer available', ['student' => $this->studentName()]))
            ->line($this->reason())
            ->line(__('Your invitation to :project was closed, because a student works on one project at a time. They become available again once that project is finished.', [
                'project' => $project->title,
            ]))
            ->action(__('Find other students'), route('recruit.index', [
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
            'type' => 'invitation.closed',
            'application_id' => $this->invitation->id,
            'project_id' => $this->invitation->project_id,
            'project_slug' => $this->invitation->project->slug,
            'project_title' => $this->invitation->project->title,
            'student_id' => $this->invitation->user_id,
            'student_name' => $this->studentName(),
            'accepted_invitation' => $this->acceptedAnInvitation(),
        ];
    }

    /**
     * What happened, in the words the business needs.
     */
    public function reason(): string
    {
        return $this->acceptedAnInvitation()
            ? __(':student has already accepted an invitation from another client.', ['student' => $this->studentName()])
            : __(':student has already been taken on by another client.', ['student' => $this->studentName()]);
    }

    /**
     * Whether the student took an invitation, rather than being accepted on
     * an application they sent.
     */
    protected function acceptedAnInvitation(): bool
    {
        return $this->taken->source === ApplicationSource::Invited;
    }

    protected function studentName(): string
    {
        return $this->invitation->student->name;
    }
}
