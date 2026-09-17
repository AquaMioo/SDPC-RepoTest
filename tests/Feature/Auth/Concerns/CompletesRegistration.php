<?php

namespace Tests\Feature\Auth\Concerns;

use App\Enums\UserRole;
use App\Notifications\Auth\EmailOneTimePassword;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

/**
 * Walks a sign up all the way through the emailed code.
 *
 * Registration is two requests now: the form, then the code. Tests that are
 * about what registering produces — the team, the profile, the redirect —
 * should not each rebuild that walk.
 */
trait CompletesRegistration
{
    /**
     * Submit the sign up form and confirm the code it triggers.
     *
     * A student has no separate email: the code goes to the school address,
     * which becomes the account's address.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function completeRegistration(array $payload): TestResponse
    {
        Notification::fake();

        $email = ($payload['role'] ?? null) === UserRole::Student->value
            ? mb_strtolower(trim($payload['school_email']))
            : $payload['email'];

        $this->post(route('register.store'), $payload)
            ->assertRedirect(route('register.verify'));

        // Nothing exists yet. That is the whole point of the extra step.
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => $email]);

        return $this->post(route('register.verify.store'), [
            'code' => $this->lastCodeSentTo($email),
        ]);
    }

    /**
     * Read the code that was actually mailed to an address.
     *
     * There is nowhere else to get it: the table stores a hash.
     */
    protected function lastCodeSentTo(string $email): string
    {
        $code = null;

        Notification::assertSentOnDemand(
            EmailOneTimePassword::class,
            function (EmailOneTimePassword $notification, array $channels, AnonymousNotifiable $notifiable) use (&$code, $email): bool {
                if ($notifiable->routes['mail'] !== mb_strtolower($email)) {
                    return false;
                }

                $code = $notification->code;

                return true;
            },
        );

        Assert::assertNotNull($code, "No verification code was mailed to {$email}.");

        return $code;
    }
}
