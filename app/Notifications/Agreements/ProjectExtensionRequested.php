<?php

namespace App\Notifications\Agreements;

use App\Models\Addendum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the student side when the client asks to extend the project.
 *
 * The student signs the same addendum, so they hear about it the moment it is
 * opened rather than finding it later; Project Management says the same.
 */
class ProjectExtensionRequested extends Notification implements ShouldQueue
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
            ->subject(__('The client wants to extend :project', [
                'project' => $this->addendum->agreement->project->title,
            ]))
            ->line(__('The client opened a Payment & Project Extension Addendum (:reference).', [
                'reference' => $this->addendum->reference,
            ]))
            ->line(__('Add the extended services to Section II, agree on the target amount, then sign it on SDPC.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return AddendumPayload::for($this->addendum, 'addendum.requested');
    }
}
