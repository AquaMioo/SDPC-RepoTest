import { router, usePage } from '@inertiajs/react';
import { useEchoNotification } from '@laravel/echo-react';
import { useEffect } from 'react';
import { toast } from 'sonner';

import { isAccountHeldInUse } from '@/lib/account-session';
import { edit as securityEdit } from '@/routes/security';
import { heartbeat, leave } from '@/routes/session';

type SharedProps = {
    auth?: { user?: { id: number } | null };
};

type AccessBlocked = {
    device?: string;
    ip_address?: string | null;
};

/* How often an open tab reports in. Well inside the server's 5-minute window. */
const BEAT_EVERY_MS = 60_000;

/*
 * How recently somebody must have touched the page for it to report in. A tab
 * nobody has used for this long stops holding the account, so an open tab on a
 * shared machine lets its owner back in from somewhere else.
 */
const ACTIVE_WITHIN_MS = 4 * 60_000;

const ACTIVITY_EVENTS = [
    'pointerdown',
    'pointermove',
    'keydown',
    'scroll',
    'touchstart',
] as const;

/*
 * The "someone tried to sign in" alert is a heads-up, not a dialog: it shows
 * briefly and the notification bell keeps it (with the change-password link).
 * It used to stay until closed, and because the toaster sits above every
 * page, it was still on screen after the account had logged out.
 */
const ACCESS_BLOCKED_TOAST = 'account-access-blocked';
const ACCESS_BLOCKED_SHOWN_FOR_MS = 2_000;

/*
 * Every open tab of the account writes itself into localStorage, so the one
 * closing can tell whether it is the last. A tab refreshes its entry on a
 * timer, and a browser may slow a background tab's timers to once a minute,
 * so an entry only counts as gone well after that.
 */
const OPEN_TABS_KEY = 'sdpc.open-tabs';
const TAB_ALIVE_EVERY_MS = 20_000;
const TAB_GONE_AFTER_MS = 90_000;

/*
 * How long after a click on a plain link or form an unload still counts as
 * the page navigating, not the tab closing. The new page starts loading as
 * soon as the click lands; this only covers a slow server answering it.
 */
const NAVIGATION_COUNTS_FOR_MS = 10_000;

/*
 * When a freshly loaded page reports in. Well inside the server's grace for
 * a refresh (AccountSession::LEAVE_GRACE_SECONDS, 30 s), and late enough that
 * the "last tab closed" signal from the page it replaced has landed first.
 */
const REPORT_IN_AFTER_LOAD_MS = 3_000;

type OpenTabs = Record<string, number>;

/**
 * Read, change and write back this account's open-tab list.
 *
 * Returns null when storage is unavailable (a private window, blocked site
 * data) — the caller then cannot see other tabs and treats itself as alone.
 */
function updateOpenTabs(
    key: string,
    change: (tabs: OpenTabs) => OpenTabs,
): OpenTabs | null {
    try {
        const stored = JSON.parse(
            window.localStorage.getItem(key) ?? '{}',
        ) as OpenTabs;
        const alive = Object.fromEntries(
            Object.entries(stored).filter(
                ([, seen]) => Date.now() - seen < TAB_GONE_AFTER_MS,
            ),
        );
        const next = change(alive);

        window.localStorage.setItem(key, JSON.stringify(next));

        return next;
    } catch {
        return null;
    }
}

/**
 * Tell the server this browser has gone, as the page unloads.
 *
 * keepalive lets the request outlive the tab. If it never arrives, the
 * server's presence window frees the account a few minutes later anyway.
 */
function sendLeave(): void {
    const xsrf = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    void fetch(leave.url(), {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(xsrf ?? ''),
        },
    }).catch(() => undefined);
}

/**
 * Keeps this device's hold on the account, and warns when somebody else tries.
 *
 * One account may be used on one device at a time (App\Support\AccountSession).
 * The server decides "in use" from the last time it heard from the account, and
 * reading a page or sitting in a call makes no requests — so this reports in
 * while the page is really being used.
 *
 * If the answer is that this device no longer holds the account, it is sent to
 * the login screen, where the reason is waiting.
 *
 * Mounted once by each signed-in shell.
 */
export default function AccountSessionGuard() {
    const userId = usePage<SharedProps>().props.auth?.user?.id;

    if (!userId) {
        return null;
    }

    return <Guard userId={userId} />;
}

function Guard({ userId }: { userId: number }) {
    useEffect(() => {
        let lastActivity = Date.now();
        let inFlight = false;

        const beat = async (always = false): Promise<void> => {
            const held = isAccountHeldInUse();
            const visible = document.visibilityState === 'visible';
            const recentlyUsed = Date.now() - lastActivity < ACTIVE_WITHIN_MS;

            if (inFlight || (!always && !held && !(visible && recentlyUsed))) {
                return;
            }

            inFlight = true;

            try {
                const response = await fetch(heartbeat.url(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (response.status === 409) {
                    const body = (await response.json().catch(() => null)) as {
                        redirect?: string;
                    } | null;

                    router.visit(body?.redirect ?? '/login');
                }
            } catch {
                /* Offline for a moment. The next beat tries again. */
            } finally {
                inFlight = false;
            }
        };

        const markActive = (): void => {
            lastActivity = Date.now();
        };

        /*
         * Coming back to the tab reports in straight away, so a device that
         * lost the account while it was in the background finds out now
         * rather than on the next tick.
         */
        const onVisibilityChange = (): void => {
            if (document.visibilityState === 'visible') {
                markActive();
                void beat();
            }
        };

        for (const event of ACTIVITY_EVENTS) {
            window.addEventListener(event, markActive, { passive: true });
        }

        document.addEventListener('visibilitychange', onVisibilityChange);

        const timer = window.setInterval(() => void beat(), BEAT_EVERY_MS);

        /*
         * A refresh sends the same "last tab closed" signal as closing does,
         * and the server signs a browser that was not kept logged in out if
         * it has not heard back within AccountSession::LEAVE_GRACE_SECONDS.
         * So a freshly loaded page reports in once, whatever its state — late
         * enough that the signal from the page it replaced has arrived first.
         */
        const arrival = window.setTimeout(
            () => void beat(true),
            REPORT_IN_AFTER_LOAD_MS,
        );

        return () => {
            for (const event of ACTIVITY_EVENTS) {
                window.removeEventListener(event, markActive);
            }

            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );
            window.clearInterval(timer);
            window.clearTimeout(arrival);
        };
    }, []);

    /*
     * Closing the last tab frees the account at once. Without this the server
     * only notices the person has gone when the presence window runs out, and
     * for those minutes a second browser — often the same person's — is told
     * the account is in use. Closing one of several tabs frees nothing.
     *
     * The same signal decides "Keep me logged in": a browser without it is
     * signed out when it comes back (AccountSession::leave()). So leaving
     * through a link or form on the page itself — a full-page load such as
     * starting to link a Google account — is not a close, and says nothing.
     * Inertia's own visits never unload the page, and they call
     * preventDefault(), which is how they are told apart here.
     */
    useEffect(() => {
        const key = `${OPEN_TABS_KEY}.${userId}`;
        const tabId = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        let navigatingAt = 0;

        const onClick = (event: MouseEvent): void => {
            const link =
                event.target instanceof Element
                    ? event.target.closest('a[href]')
                    : null;

            if (
                link === null ||
                event.defaultPrevented ||
                event.button !== 0 ||
                event.ctrlKey ||
                event.metaKey ||
                event.shiftKey ||
                event.altKey ||
                link.hasAttribute('download') ||
                !['', '_self'].includes(link.getAttribute('target') ?? '')
            ) {
                return;
            }

            navigatingAt = Date.now();
        };

        const onSubmit = (event: SubmitEvent): void => {
            if (!event.defaultPrevented) {
                navigatingAt = Date.now();
            }
        };

        const register = (): void => {
            updateOpenTabs(key, (tabs) => ({ ...tabs, [tabId]: Date.now() }));
        };

        const forget = (): OpenTabs | null =>
            updateOpenTabs(key, (tabs) =>
                Object.fromEntries(
                    Object.entries(tabs).filter(([id]) => id !== tabId),
                ),
            );

        const onPageHide = (): void => {
            const others = forget();
            const leavingThroughThePage =
                Date.now() - navigatingAt < NAVIGATION_COUNTS_FOR_MS;

            if (
                !leavingThroughThePage &&
                (others === null || Object.keys(others).length === 0)
            ) {
                sendLeave();
            }
        };

        register();

        const timer = window.setInterval(register, TAB_ALIVE_EVERY_MS);

        /* Bubbling, so Inertia's handlers have already run by now. */
        document.addEventListener('click', onClick);
        document.addEventListener('submit', onSubmit);
        window.addEventListener('pagehide', onPageHide);
        /* Restored from the back/forward cache: open again. */
        window.addEventListener('pageshow', register);

        return () => {
            window.clearInterval(timer);
            document.removeEventListener('click', onClick);
            document.removeEventListener('submit', onSubmit);
            window.removeEventListener('pagehide', onPageHide);
            window.removeEventListener('pageshow', register);
            forget();
        };
    }, [userId]);

    /*
     * The alert belongs to the signed-in account. The guard unmounts when the
     * account logs out (or another one takes its place), so take the alert
     * down with it rather than leaving it over the public pages.
     */
    useEffect(() => () => void toast.dismiss(ACCESS_BLOCKED_TOAST), [userId]);

    useEchoNotification<AccessBlocked>(
        `App.Models.User.${userId}`,
        (notification) => {
            const device = [
                notification.device ?? 'A device',
                notification.ip_address ? `(${notification.ip_address})` : null,
            ]
                .filter(Boolean)
                .join(' ');

            /*
             * One id, so a second attempt replaces the alert rather than
             * stacking another, and logging out can find it to dismiss.
             */
            toast.warning('Someone tried to sign in to your account', {
                id: ACCESS_BLOCKED_TOAST,
                description: `${device} was blocked because you are using the account. If it was not you, change your password.`,
                duration: ACCESS_BLOCKED_SHOWN_FOR_MS,
                closeButton: true,
                action: {
                    label: 'Change password',
                    onClick: () => router.visit(securityEdit.url()),
                },
            });

            /* Already stored as a bell entry; this brings the bell up to date. */
            router.reload({
                only: ['unreadNotifications', 'recentNotifications'],
            });
        },
        'account.access_blocked',
        [userId],
    );

    return null;
}
