<?php

namespace App\Notifications\Agreements;

use App\Enums\AgreementParty;
use App\Models\Addendum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to whichever side has not signed the addendum yet.
 */
class AddendumSigned extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Addendum $addendum,
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
            ->subject(__('Your signature is needed on addendum :reference', [
                'reference' => $this->addendum->reference,
            ]))
            ->line(__('The :party signed the project extension addendum for :project.', [
                'party' => strtolower($this->signedBy->label()),
                'project' => $this->addendum->agreement->project->title,
            ]))
            ->line(__('The down payment falls due once both sides have signed.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            ...AddendumPayload::for($this->addendum, 'addendum.signed'),
            'signed_by' => $this->signedBy->value,
        ];
    }
}
