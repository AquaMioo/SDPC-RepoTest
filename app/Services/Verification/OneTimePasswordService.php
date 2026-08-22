<?php

namespace App\Services\Verification;

use App\Enums\OneTimePasswordPurpose;
use App\Enums\OneTimePasswordResult;
use App\Models\OneTimePassword;
use App\Notifications\Auth\EmailOneTimePassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * The emailed code that proves somebody can open the address they typed.
 *
 * Two callers, one mechanism: finishing a registration, and opening an appeal
 * for an account that can no longer sign in. Neither can ask for a password —
 * one has no account yet, the other may have been created through Google and
 * therefore never had one.
 *
 * The plain code exists only between being generated and being handed to the
 * mailer. What is stored is a hash, so a leaked row is not a working code.
 */
class OneTimePasswordService
{
    /**
     * Issue a code and mail it, unless one was sent moments ago.
     *
     * Returns false when the resend floor has not elapsed. The previously sent
     * code is still valid in that case, so the caller should carry on rather
     * than treat it as a failure — it only means no second email went out.
     */
    public function send(string $email, OneTimePasswordPurpose $purpose): bool
    {
        $email = $this->normalise($email);

        if ($this->secondsUntilResend($email, $purpose) > 0) {
            return false;
        }

        $code = $this->generate();

        OneTimePassword::query()->updateOrCreate(
            ['email' => $email, 'purpose' => $purpose],
            [
                'code_hash' => Hash::make($code),
                /* A fresh code starts a fresh budget of guesses. */
                'attempts' => 0,
                'expires_at' => now()->addMinutes((int) config('otp.expires_after')),
                'sent_at' => now(),
            ],
        );

        Notification::route('mail', $email)
            ->notify(new EmailOneTimePassword($code, $purpose));

        return true;
    }

    /**
     * Check a code, spending one attempt whether or not it matches.
     *
     * A correct code is consumed on the way out: it opens one door once, and
     * pressing back must not open it again.
     */
    public function check(string $email, OneTimePasswordPurpose $purpose, string $code): OneTimePasswordResult
    {
        $record = $this->find($this->normalise($email), $purpose);

        if ($record === null) {
            return OneTimePasswordResult::Missing;
        }

        if ($record->hasExpired()) {
            return OneTimePasswordResult::Expired;
        }

        if ($record->isExhausted()) {
            return OneTimePasswordResult::Exhausted;
        }

        $record->increment('attempts');

        if (! Hash::check($code, $record->code_hash)) {
            /*
             * Told apart from Exhausted on purpose: the guess that spends the
             * last attempt should say the code was wrong, not that the budget
             * ran out before it was read.
             */
            return OneTimePasswordResult::Mismatch;
        }

        $record->delete();

        return OneTimePasswordResult::Valid;
    }

    /**
     * How long before another code may be sent to this address.
     */
    public function secondsUntilResend(string $email, OneTimePasswordPurpose $purpose): int
    {
        return $this->find($this->normalise($email), $purpose)?->secondsUntilResend() ?? 0;
    }

    /**
     * Drop any code held for this address and purpose.
     *
     * Called when the flow it belonged to is abandoned — changing the address
     * on the signup form, say — so an orphaned code cannot be typed into a
     * later attempt.
     */
    public function forget(string $email, OneTimePasswordPurpose $purpose): void
    {
        $this->find($this->normalise($email), $purpose)?->delete();
    }

    /**
     * Find the live code for an address and purpose, if there is one.
     */
    private function find(string $email, OneTimePasswordPurpose $purpose): ?OneTimePassword
    {
        return OneTimePassword::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->first();
    }

    /**
     * Build a zero-padded numeric code of the configured length.
     */
    private function generate(): string
    {
        $length = (int) config('otp.length');

        return str_pad(
            (string) random_int(0, (10 ** $length) - 1),
            $length,
            '0',
            STR_PAD_LEFT,
        );
    }

    /**
     * Key on the address the same way the users table stores it.
     *
     * config('fortify.lowercase_usernames') is true, so "Ada@example.com" and
     * "ada@example.com" are one account — and must be one code, or asking for
     * a second would leave the first still standing.
     */
    private function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
