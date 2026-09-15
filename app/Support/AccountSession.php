<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\Auth\AccountAccessBlocked;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * One account, one device at a time.
 *
 * The session that holds the account keeps a random token in its own data, and
 * the same token sits on users.active_session_token. Every signed-in request is
 * checked against it by App\Http\Middleware\EnforceSingleSession, so there is
 * no sign-in path that gets around it: a password, a two-factor challenge,
 * Google, a new registration and a "remember me" cookie all end up here.
 *
 * A session without a token for the account asks to hold it. That is granted
 * only when nobody is using the account — the holder's presence stamp
 * (users.last_seen_at, see User::isOnline()) is empty or older than the
 * presence window. Signing out nulls that stamp, so the account frees the moment
 * its holder leaves; closing the browser frees it once the window runs out.
 * While the holder is here, anybody else is refused, and the holder is told.
 *
 * A session that holds a token which no longer matches was replaced: somebody
 * claimed the account after it went quiet, or a password reset took it back.
 * It is signed out and cannot claim again with that session.
 */
final class AccountSession
{
    /**
     * Shown to a device turned away because the account is in use elsewhere.
     */
    public const IN_USE = 'This account is already being used on another device. Only one device can use an account at a time, so this sign-in was blocked and the account holder has been alerted. If that was you, sign out on the other device first.';

    /**
     * Shown to a device whose hold on the account was taken over.
     */
    public const REPLACED = 'You were signed out because this account was signed in on another device, or its password was reset.';

    /**
     * How often the account holder is alerted about refused sign-ins.
     */
    private const ALERT_DECAY_SECONDS = 60;

    /**
     * Decide whether this request's session may act as the user.
     *
     * Returns null when it may — it already holds the account, or has just
     * claimed it — and the message to show the person otherwise.
     */
    public function admit(Request $request, User $user): ?string
    {
        $mine = $request->session()->get($this->sessionKey($user));
        $held = $user->active_session_token;

        if (is_string($mine) && is_string($held) && hash_equals($held, $mine)) {
            return null;
        }

        if (is_string($mine)) {
            return self::REPLACED;
        }

        if ($this->claim($request, $user)) {
            return null;
        }

        $this->alertHolder($request, $user);

        return self::IN_USE;
    }

    /**
     * Give the account up, so the next device to sign in may claim it.
     *
     * Every session holding the old token is signed out on its next request.
     */
    public function release(User $user): void
    {
        User::withoutTimestamps(fn () => $user->forceFill([
            'active_session_token' => null,
            'last_seen_at' => null,
        ])->saveQuietly());
    }

    /**
     * Take the account for this session if nobody is using it.
     *
     * One conditional UPDATE rather than a read and then a write, so two
     * devices signing in at the same moment cannot both come away holding it.
     * Stamping last_seen_at in the same statement is what makes the claim
     * count as "in use" straight away, before the holder's next page view.
     */
    private function claim(Request $request, User $user): bool
    {
        $token = Str::random(64);
        $now = now();
        $cutoff = $now->copy()->subMinutes(User::PRESENCE_WINDOW_MINUTES);

        $claimed = User::query()
            ->whereKey($user->getKey())
            ->where(fn ($query) => $query
                /* Never claimed since the column arrived, or released by a reset. */
                ->whereNull('active_session_token')
                ->orWhereNull('last_seen_at')
                ->orWhere('last_seen_at', '<=', $cutoff))
            ->toBase()
            ->update([
                'active_session_token' => $token,
                'last_seen_at' => $now,
            ]) === 1;

        if (! $claimed) {
            return false;
        }

        $user->forceFill(['active_session_token' => $token, 'last_seen_at' => $now])->syncOriginal();

        $request->session()->put($this->sessionKey($user), $token);

        return true;
    }

    /**
     * Tell the account holder somebody else just got past the password.
     *
     * Only reached after the refused device authenticated, so it is never a
     * signal an address alone can trigger. Limited to one alert a minute so a
     * device retrying in a loop cannot flood the bell.
     *
     * A failure to deliver must not undo the refusal: Reverb being down is a
     * reason to log, not a reason to let the second device in.
     */
    private function alertHolder(Request $request, User $user): void
    {
        RateLimiter::attempt(
            'account-access-blocked:'.$user->getKey(),
            1,
            function () use ($request, $user): void {
                try {
                    $user->notify(new AccountAccessBlocked(
                        device: $this->describeDevice((string) $request->userAgent()),
                        ipAddress: $request->ip(),
                    ));
                } catch (Throwable $exception) {
                    report($exception);
                }
            },
            self::ALERT_DECAY_SECONDS,
        );
    }

    /**
     * A short "Chrome on Windows" reading of a user agent.
     *
     * Order matters in both lists: Edge and Opera also claim to be Chrome, and
     * Chrome also claims to be Safari; Android also claims to be Linux.
     */
    private function describeDevice(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/'), str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };

        $platform = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return match (true) {
            $browser !== null && $platform !== null => __(':browser on :platform', ['browser' => $browser, 'platform' => $platform]),
            $browser !== null => $browser,
            $platform !== null => $platform,
            default => __('An unknown device'),
        };
    }

    /**
     * Where this session keeps its token for the given account.
     *
     * Keyed by account so one browser that signs in as two people in turn
     * keeps each hold separately instead of overwriting one with the other.
     */
    private function sessionKey(User $user): string
    {
        return 'account_session.'.$user->getKey();
    }
}
