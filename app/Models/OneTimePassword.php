<?php

namespace App\Models;

use App\Enums\OneTimePasswordPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One live emailed code for one address and purpose.
 *
 * Written and read only through App\Services\Verification\OneTimePasswordService
 * — nothing else should be reaching for `code_hash`.
 *
 * @property int $id
 * @property string $email
 * @property OneTimePasswordPurpose $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon $sent_at
 */
#[Fillable(['email', 'purpose', 'code_hash', 'attempts', 'expires_at', 'sent_at'])]
#[Hidden(['code_hash'])]
class OneTimePassword extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => OneTimePasswordPurpose::class,
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Determine if the code is past its window.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Determine if the code has been guessed at too many times.
     */
    public function isExhausted(): bool
    {
        return $this->attempts >= (int) config('otp.max_attempts');
    }

    /**
     * How long the caller must wait before another code may be sent.
     */
    public function secondsUntilResend(): int
    {
        $ready = $this->sent_at->addSeconds((int) config('otp.resend_after'));

        return max(0, (int) ceil(now()->diffInSeconds($ready, absolute: false)));
    }
}
