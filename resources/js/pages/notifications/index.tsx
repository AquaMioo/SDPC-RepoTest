import { Head, Link, useForm } from '@inertiajs/react';

import { Btn } from '@/components/sdpc/btn';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { read, readAll } from '@/routes/notifications';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Notification = {
    id: string;
    title: string;
    body: string | null;
    url: string | null;
    at: string | null;
    read: boolean;
};

type Props = {
    notifications: Notification[];
    unreadCount: number;
};

/**
 * The notification centre, shared by both modules.
 *
 * One list rather than a dropdown: these rows are the record of what happened
 * to a posting or a contract, and burying them in a panel that closes on the
 * next click makes them harder to read than the emails carrying the same
 * events.
 */
export default function Notifications({ notifications, unreadCount }: Props) {
    const team = useCurrentTeam();
    const markAll = useForm({});
    const markOne = useForm({});

    return (
        <>
            <Head title="Notifications" />

            <div
                style={{
                    maxWidth: 1060,
                    margin: '0 auto',
                    padding: '30px 32px 72px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 22,
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        justifyContent: 'space-between',
                        gap: 16,
                    }}
                >
                    <div>
                        <h3 style={{ margin: 0 }}>Notifications</h3>
                        <div style={{ fontSize: 13, color: MUTED(60) }}>
                            {unreadCount > 0
                                ? `${unreadCount} unread`
                                : 'Everything here has been read.'}
                        </div>
                    </div>

                    {unreadCount > 0 && (
                        <Btn
                            variant="secondary"
                            disabled={markAll.processing}
                            onClick={() =>
                                markAll.post(readAll.url(team.slug), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Mark all read
                        </Btn>
                    )}
                </div>

                {notifications.length === 0 ? (
                    <Panel padding="lg" gap="sm">
                        <span style={{ fontSize: 13 }}>Nothing yet.</span>
                        <span style={{ fontSize: 12.5, color: MUTED(65) }}>
                            Applications, approvals and signatures on your
                            contracts land here as they happen.
                        </span>
                    </Panel>
                ) : (
                    <Panel padding="lg" gap="md">
                        <PanelKicker>
                            {notifications.length} in total
                        </PanelKicker>

                        {notifications.map((notification) => (
                            <div
                                key={notification.id}
                                style={{
                                    display: 'flex',
                                    alignItems: 'flex-start',
                                    gap: 12,
                                    paddingTop: 10,
                                    borderTop: `1px solid ${MUTED(10)}`,
                                }}
                            >
                                {/*
                                 * The dot is the only thing telling read from
                                 * unread, so it keeps its space either way and
                                 * the rows stay on one grid.
                                 */}
                                <span
                                    aria-hidden="true"
                                    style={{
                                        width: 7,
                                        height: 7,
                                        marginTop: 6,
                                        flexShrink: 0,
                                        borderRadius: '50%',
                                        background: notification.read
                                            ? 'transparent'
                                            : 'var(--color-accent)',
                                    }}
                                />

                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            fontWeight: notification.read
                                                ? 400
                                                : 600,
                                        }}
                                    >
                                        {notification.url ? (
                                            <Link
                                                href={notification.url}
                                                style={{ color: 'inherit' }}
                                            >
                                                {notification.title}
                                            </Link>
                                        ) : (
                                            notification.title
                                        )}
                                    </div>

                                    {notification.body && (
                                        <div
                                            style={{
                                                fontSize: 12.5,
                                                color: MUTED(65),
                                                marginTop: 2,
                                            }}
                                        >
                                            {notification.body}
                                        </div>
                                    )}
                                </div>

                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 10,
                                        flexShrink: 0,
                                    }}
                                >
                                    <span style={{ fontSize: 12, color: MUTED(55) }}>
                                        {notification.at}
                                    </span>

                                    {!notification.read && (
                                        <Btn
                                            variant="ghost"
                                            disabled={markOne.processing}
                                            onClick={() =>
                                                markOne.post(
                                                    read.url({
                                                        current_team: team.slug,
                                                        notification:
                                                            notification.id,
                                                    }),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Mark read
                                        </Btn>
                                    )}
                                </div>
                            </div>
                        ))}
                    </Panel>
                )}
            </div>
        </>
    );
}
