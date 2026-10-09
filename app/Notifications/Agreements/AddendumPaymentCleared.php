<?php

namespace App\Notifications\Agreements;

use App\Models\AddendumPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to both sides when a milestone clears through the gateway.
 *
 * Milestone 1 tells the student to start; Milestone 2 tells everyone the
 * extension is paid for and its files are unlocked.
 */
class AddendumPaymentCleared extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public AddendumPayment $payment) {}

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
        $addendum = $this->payment->addendum;

        return (new MailMessage)
            ->subject(__(':milestone of addendum :reference is paid', [
                'milestone' => $this->payment->milestone === 1 ? __('The down payment') : __('The final balance'),
                'reference' => $addendum->reference,
            ]))
            ->line($this->payment->milestone === 1
                ? __('The extended services are now in Project Management, under Objective & Scope. The work can start.')
                : __('The extension is fully paid. Its files and links are now open to the client.'))
            ->line(__('Invoice :invoice.', ['invoice' => $this->payment->invoice_number]));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            ...AddendumPayload::for($this->payment->addendum, 'addendum.paid'),
            'milestone' => $this->payment->milestone,
            'invoice_number' => $this->payment->invoice_number,
        ];
    }
}
