<?php

namespace App\Notifications\Auth;

use App\Enums\OneTimePasswordPurpose;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The code itself, mailed to the address being proved.
 *
 * Deliberately NOT ShouldQueue, unlike every other notification here. The
 * person is sitting on the screen waiting to type this in; on the default
 * database queue it would wait for a worker that may not be running, and the
 * signup would look broken rather than slow.
 *
 * Sent on demand — Notification::route('mail', $email) — because at both
 * moments it is used there is no usable account to notify: registration has
 * not created one yet, and an appeal comes from an account that cannot sign in.
 */
class EmailOneTimePassword extends Notification
{
    /**
     * Both properties are public so a test can read what was actually mailed.
     * There is nowhere else to get the code from: what the table holds is a
     * hash, on purpose.
     */
    public function __construct(
        public readonly string $code,
        public readonly OneTimePasswordPurpose $purpose,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('otp.expires_after');

        return (new MailMessage)
            ->subject($this->subject())
            ->greeting(__('Your verification code'))
            ->line($this->reason())
            ->line(__('Your code is: :code', ['code' => $this->code]))
            ->line(__('It expires in :minutes minutes.', ['minutes' => $minutes]))
            ->line(__('If you did not ask for this, you can ignore this email — nothing has been created or changed.'));
    }

    /**
     * The subject line, which says what the code is for.
     */
    private function subject(): string
    {
        return match ($this->purpose) {
            OneTimePasswordPurpose::Registration => __('Confirm your email address'),
            OneTimePasswordPurpose::Appeal => __('Your appeal verification code'),
        };
    }

    /**
     * Why this landed in their inbox.
     */
    private function reason(): string
    {
        return match ($this->purpose) {
            OneTimePasswordPurpose::Registration => __('Somebody used this address to sign up for SDPC. Enter the code below to finish creating the account.'),
            OneTimePasswordPurpose::Appeal => __('Somebody asked to appeal a decision on the SDPC account at this address. Enter the code below to continue.'),
        };
    }
}
