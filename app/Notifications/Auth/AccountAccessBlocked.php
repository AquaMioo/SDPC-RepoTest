<?php

namespace App\Notifications\Auth;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Somebody signed in to an account that was already in use, and was refused.
 *
 * Sent to the account holder by App\Support\AccountSession. The refused device
 * got past the password (or Google), so this is worth an interruption: the
 * holder should change the password if it was not them.
 *
 * Deliberately NOT ShouldQueue, and the broadcast rides the sync connection:
 * a warning that somebody is at the door is useless once a worker gets round to
 * it. Database as well as broadcast, so an admin or a holder whose tab was not
 * listening still finds it in the bell.
 */
class AccountAccessBlocked extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string $device,
        public readonly ?string $ipAddress,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }

    /**
     * The name the browser listens for.
     */
    public function broadcastType(): string
    {
        return 'account.access_blocked';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account.access_blocked',
            'device' => $this->device,
            'ip_address' => $this->ipAddress,
            'attempted_at' => now()->toIso8601String(),
        ];
    }
}
