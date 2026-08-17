<?php

namespace App\Notifications\Agreements;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when one side asks for the terms to be revised.
 *
 * Carries the note verbatim: the reason for a change request is the whole
 * content of it, and summarising somebody's objection would be the platform
 * putting words in their mouth.
 */
class ChangesRequested extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Agreement $agreement,
        public User $requestedBy,
        public string $note,
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
            ->subject(__('Changes requested on :reference', [
                'reference' => $this->agreement->reference,
            ]))
            ->line(__(':name asked for changes to the agreement for :project.', [
                'name' => $this->requestedBy->name,
                'project' => $this->agreement->project->title,
            ]))
            ->line($this->note)
            ->line(__('A new version has been drafted. Both sides sign again once the terms are settled.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'agreement.changes_requested',
            'agreement_id' => $this->agreement->id,
            'agreement_reference' => $this->agreement->reference,
            'requested_by' => $this->requestedBy->id,
            'note' => $this->note,
            'project_id' => $this->agreement->project_id,
            'project_title' => $this->agreement->project->title,
        ];
    }
}
