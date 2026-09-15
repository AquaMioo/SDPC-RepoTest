<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\AccountSession;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Resetting a password takes the account back from whoever holds it.
 *
 * One device at a time cuts both ways: if the device holding the account is
 * not the owner's, the owner cannot sign in past it. The forgotten-password
 * email is the way back in — whoever can read that inbox releases the hold, the
 * device holding it is signed out on its next request, and the owner's next
 * sign-in claims the account. Fortify has already cycled the remember token by
 * the time this runs, so a "remember me" cookie cannot walk back in either.
 *
 * Picked up by event discovery, like the other listeners in this directory.
 */
class ReleaseAccountSession
{
    public function __construct(private readonly AccountSession $accountSession) {}

    public function handle(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->accountSession->release($event->user);
        }
    }
}
