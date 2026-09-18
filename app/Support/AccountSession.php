<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\Auth\AccountAccessBlocked;
use Illuminate\Auth\Recaller;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
 * its holder leaves; closing the last tab does the same (leave()), and a
 * browser that could not say so frees it once the window runs out. While the
 * holder is here, anybody else is refused, and the holder is told.
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
     * Shown to a browser that closed SDPC without "Keep me logged in".
     */
    public const CLOSED = 'You were signed out because SDPC was closed. Tick "Keep me logged in" when you sign in to stay signed in on this device.';

    /**
     * How often the account holder is alerted about refused sign-ins.
     */
    private const ALERT_DECAY_SECONDS = 60;

    /**
     * How long a browser that said it left may take to come back and still be
     * the same visit.
     *
     * A refresh fires the same "last tab closed" signal as closing does — the
     * page cannot tell them apart — so the reloaded page has this long to
     * report in (it does within seconds) before the visit counts as over.
     */
    public const LEAVE_GRACE_SECONDS = 30;

    /**
     * Decide whether this request's session may act as the user.
     *
     * Returns null when it may — it already holds the account, or has just
     * claimed it — and the message to show the person otherwise.
     */
    public function admit(Request $request, User $user): ?string
    {
        if ($this->holds($request, $user)) {
            return $this->cameBackInTime($request, $user) ? null : self::CLOSED;
        }

        if (is_string($request->session()->get($this->sessionKey($user)))) {
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
     * Say this device has gone, without giving up its hold.
     *
     * Sent by the page when the last tab on it closes. "In use" is read off
     * the presence stamp, so nulling it lets another device sign in straight
     * away rather than wait out the presence window — which is what left a
     * closed tab locking its own owner out of a second browser for minutes.
     *
     * The token stays, and what happens next depends on "Keep me logged in".
     * A remembered browser comes back to the same session, is admitted as the
     * holder, and its next request stamps it present again. One that was not
     * remembered is marked as left: unless it reports back within
     * LEAVE_GRACE_SECONDS — a refresh does — its next request signs it out
     * (see cameBackInTime()), so the next person at a shared computer does not
     * find the account still open.
     *
     * Only the holder may say it has left: a replaced session must never free
     * an account somebody else is using.
     */
    public function leave(Request $request, User $user): void
    {
        if (! $this->holds($request, $user)) {
            return;
        }

        User::withoutTimestamps(
            fn () => $user->forceFill(['last_seen_at' => null])->saveQuietly(),
        );

        if (! $this->isRemembered($request, $user)) {
            $request->session()->put($this->leftKey($user), now()->getTimestamp());
        }
    }

    /**
     * Whether this request's session is the one holding the account.
     */
    private function holds(Request $request, User $user): bool
    {
        $mine = $request->session()->get($this->sessionKey($user));
        $held = $user->active_session_token;

        return is_string($mine) && is_string($held) && hash_equals($held, $mine);
    }

    /**
     * Whether a holder that said it left is back soon enough to be the same visit.
     *
     * True when it never said so. The mark is used up either way: a refresh
     * carries on as before, and a late return is signed out by the caller.
     */
    private function cameBackInTime(Request $request, User $user): bool
    {
        $leftAt = $request->session()->pull($this->leftKey($user));

        if (! is_int($leftAt)) {
            return true;
        }

        return now()->getTimestamp() - $leftAt <= self::LEAVE_GRACE_SECONDS;
    }

    /**
     * Whether this browser carries a valid "Keep me logged in" cookie for the user.
     *
     * Read off the cookie rather than remembered from the sign-in, because the
     * cookie is what actually signs the browser back in: an older one left
     * from an earlier remembered sign-in still would, and one cleared by
     * signing out no longer can.
     */
    private function isRemembered(Request $request, User $user): bool
    {
        $guard = Auth::guard((string) config('fortify.guard'));

        if (! $guard instanceof SessionGuard) {
            return false;
        }

        $cookie = $request->cookies->get($guard->getRecallerName());

        if (! is_string($cookie) || $cookie === '') {
            return false;
        }

        $recaller = new Recaller($cookie);

        return $recaller->valid()
            && (string) $recaller->id() === (string) $user->getAuthIdentifier()
            && hash_equals((string) $user->getRememberToken(), $recaller->token());
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

    /**
     * Where this session notes when it said it had left.
     *
     * A key of its own rather than beside the token: sessionKey() holds a
     * string, and dot notation cannot nest under one.
     */
    private function leftKey(User $user): string
    {
        return 'account_session_left.'.$user->getKey();
    }
}
