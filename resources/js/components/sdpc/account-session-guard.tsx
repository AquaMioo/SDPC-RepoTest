import { router, usePage } from '@inertiajs/react';
import { useEchoNotification } from '@laravel/echo-react';
import { useEffect } from 'react';
import { toast } from 'sonner';

import { isAccountHeldInUse } from '@/lib/account-session';
import { edit as securityEdit } from '@/routes/security';
import { heartbeat } from '@/routes/session';

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

        const beat = async (): Promise<void> => {
            const held = isAccountHeldInUse();
            const visible = document.visibilityState === 'visible';
            const recentlyUsed = Date.now() - lastActivity < ACTIVE_WITHIN_MS;

            if (inFlight || (!held && !(visible && recentlyUsed))) {
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

        return () => {
            for (const event of ACTIVITY_EVENTS) {
                window.removeEventListener(event, markActive);
            }

            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );
            window.clearInterval(timer);
        };
    }, []);

    useEchoNotification<AccessBlocked>(
        `App.Models.User.${userId}`,
        (notification) => {
            const device = [
                notification.device ?? 'A device',
                notification.ip_address ? `(${notification.ip_address})` : null,
            ]
                .filter(Boolean)
                .join(' ');

            toast.warning('Someone tried to sign in to your account', {
                description: `${device} was blocked because you are using the account. If it was not you, change your password.`,
                duration: Infinity,
                closeButton: true,
                action: {
                    label: 'Change password',
                    onClick: () => router.visit(securityEdit.url()),
                },
            });

            router.reload({
                only: ['unreadNotifications', 'recentNotifications'],
            });
        },
        'account.access_blocked',
        [userId],
    );

    return null;
}
