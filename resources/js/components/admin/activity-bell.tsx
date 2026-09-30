import { Link, router, usePage } from '@inertiajs/react';
import { BellIcon } from '@phosphor-icons/react';
import { useEffect, useRef, useState } from 'react';

import { seen as activitySeen } from '@/routes/admin/activity';

type ActivityItem = {
    id: string;
    kind: 'signup' | 'report' | 'appeal' | 'feedback';
    title: string;
    body: string | null;
    at: string;
    ago: string;
    url: string;
    unread: boolean;
};

type AdminActivity = { items: ActivityItem[]; unread: number };

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const KIND_LABEL: Record<ActivityItem['kind'], string> = {
    signup: 'New account',
    report: 'Report',
    appeal: 'Appeal',
    feedback: 'Feedback',
};

/**
 * The administrator's bell: new accounts, reports and feedback, newest first.
 *
 * The count is whatever arrived since the menu was last opened; opening it
 * tells the server so (AdminActivityController), and the count clears. The
 * panel is drawn in place rather than in a portal, so it keeps the admin
 * palette the header sets.
 */
export default function ActivityBell() {
    const { adminActivity } = usePage<{ adminActivity: AdminActivity | null }>()
        .props;
    const [open, setOpen] = useState(false);
    const wrapper = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const closeOnOutside = (event: MouseEvent) => {
            if (!wrapper.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', closeOnOutside);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('mousedown', closeOnOutside);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [open]);

    const items = adminActivity?.items ?? [];
    const unread = adminActivity?.unread ?? 0;

    const toggle = () => {
        const opening = !open;

        setOpen(opening);

        if (opening && unread > 0) {
            router.post(
                activitySeen.url(),
                {},
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['adminActivity'],
                },
            );
        }
    };

    return (
        <div ref={wrapper} style={{ position: 'relative' }}>
            <button
                type="button"
                className="btn btn-icon"
                aria-label={
                    unread > 0
                        ? `Notifications, ${unread} new`
                        : 'Notifications'
                }
                aria-expanded={open}
                data-test="admin-bell"
                onClick={toggle}
                style={{ color: 'var(--color-text)', position: 'relative' }}
            >
                <BellIcon />
                {unread > 0 && (
                    <span
                        data-test="admin-bell-count"
                        style={{
                            position: 'absolute',
                            top: 2,
                            right: 2,
                            minWidth: 16,
                            height: 16,
                            padding: '0 4px',
                            borderRadius: 8,
                            background: 'var(--color-accent)',
                            color: '#fff',
                            fontSize: 10,
                            lineHeight: '16px',
                            textAlign: 'center',
                        }}
                    >
                        {unread > 9 ? '9+' : unread}
                    </span>
                )}
            </button>

            {open && (
                <div
                    className="card elev-md"
                    role="dialog"
                    aria-label="Recent activity"
                    style={{
                        position: 'absolute',
                        right: 0,
                        top: 'calc(100% + 8px)',
                        width: 340,
                        maxWidth: 'calc(100vw - 32px)',
                        maxHeight: 420,
                        overflowY: 'auto',
                        padding: 0,
                        zIndex: 30,
                        fontSize: 13,
                    }}
                >
                    <div
                        style={{
                            padding: '12px 14px',
                            fontWeight: 600,
                            borderBottom: `1px solid ${MUTED(12)}`,
                        }}
                    >
                        Recent activity
                    </div>

                    {items.length === 0 ? (
                        <div style={{ padding: 14, color: MUTED(55) }}>
                            Nothing yet. New accounts, reports and feedback show
                            up here.
                        </div>
                    ) : (
                        items.map((item) => (
                            <Link
                                key={item.id}
                                href={item.url}
                                onClick={() => setOpen(false)}
                                style={{
                                    display: 'block',
                                    padding: '10px 14px',
                                    borderBottom: `1px solid ${MUTED(8)}`,
                                    color: 'inherit',
                                    textDecoration: 'none',
                                    background: item.unread
                                        ? 'color-mix(in srgb, var(--color-accent) 10%, transparent)'
                                        : undefined,
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: 11,
                                        color: MUTED(55),
                                        marginBottom: 2,
                                    }}
                                >
                                    {KIND_LABEL[item.kind]} · {item.ago}
                                </div>
                                <div>{item.title}</div>
                                {item.body && (
                                    <div
                                        style={{
                                            fontSize: 12,
                                            color: MUTED(65),
                                            marginTop: 2,
                                        }}
                                    >
                                        {item.body}
                                    </div>
                                )}
                            </Link>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}
