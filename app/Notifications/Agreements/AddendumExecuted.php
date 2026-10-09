<?php

namespace App\Notifications\Agreements;

use App\Models\Addendum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to both sides when the second signature lands: the down payment is due,
 * and the extended work starts once it clears.
 */
class AddendumExecuted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Addendum $addendum) {}

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
            ->subject(__('Addendum :reference is signed', [
                'reference' => $this->addendum->reference,
            ]))
            ->line(__('Both sides signed the project extension addendum for :project.', [
                'project' => $this->addendum->agreement->project->title,
            ]))
            ->line(__('Milestone 1, the down payment, is now due through PayMongo. The extended work starts once it clears.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return AddendumPayload::for($this->addendum, 'addendum.executed');
    }
}
