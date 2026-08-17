<?php

namespace App\Notifications\Agreements;

use App\Enums\AgreementParty;
use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to whichever side has not signed yet.
 *
 * An agreement waiting on one signature is the one place work can stall
 * without anybody noticing, so the other party is told rather than left to
 * check the screen.
 */
class AgreementSigned extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Agreement $agreement,
        public AgreementParty $signedBy,
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
            ->subject(__('Your signature is needed on :reference', [
                'reference' => $this->agreement->reference,
            ]))
            ->line(__('The :party signed the agreement for :project.', [
                'party' => strtolower($this->signedBy->label()),
                'project' => $this->agreement->project->title,
            ]))
            ->line(__('The project starts once both sides have signed.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'agreement.signed',
            'agreement_id' => $this->agreement->id,
            'agreement_reference' => $this->agreement->reference,
            'signed_by' => $this->signedBy->value,
            'project_id' => $this->agreement->project_id,
            'project_title' => $this->agreement->project->title,
        ];
    }
}
