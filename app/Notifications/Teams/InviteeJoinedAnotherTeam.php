<?php

namespace App\Notifications\Teams;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a student whose team invitation was cancelled because the person
 * they invited joined another team.
 *
 * A student is on one team at a time (App\Actions\Teams\JoinTeam), so the
 * other invitations could never be accepted once one was. They are cancelled
 * when the student joins, and whoever sent each one is told.
 *
 * Deliberately NOT ShouldQueue: there is no queue worker on this deployment,
 * and the inviter is waiting on this answer.
 */
class InviteeJoinedAnotherTeam extends Notification
{
    /**
     * @param  TeamInvitation  $invitation  the cancelled invitation (already deleted; read, not saved)
     * @param  Team  $joined  the team the student joined instead
     */
    public function __construct(
        public TeamInvitation $invitation,
        public User $student,
        public Team $joined,
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
        $team = $this->invitation->team;

        return (new MailMessage)
            ->subject(__(':student joined another team', ['student' => $this->student->name]))
            ->line(__(':student has already accepted an invitation from another student and joined :joined.', [
                'student' => $this->student->name,
                'joined' => $this->joined->name,
            ]))
            ->line(__('Your invitation to :team was cancelled, because a student can only be on one team. You can invite somebody else.', [
                'team' => $team->name,
            ]))
            ->action(__('Invite a teammate'), route('teams.edit', ['team' => $team->slug]));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'team.invitation_closed',
            'team_id' => $this->invitation->team_id,
            'team_name' => $this->invitation->team->name,
            'team_slug' => $this->invitation->team->slug,
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'joined_team_name' => $this->joined->name,
        ];
    }
}
