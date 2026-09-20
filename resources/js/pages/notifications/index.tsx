import { Head, Link, router } from '@inertiajs/react';
import { CheckIcon, TrashIcon } from '@phosphor-icons/react';
import { useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { Avatar } from '@/components/sdpc/notification-menu';
import type { NotificationRow } from '@/components/sdpc/notification-menu';
import { Panel } from '@/components/sdpc/panel';
import { useCurrentTeam } from '@/hooks/use-current-team';
import {
    clear,
    destroy,
    read,
    readAll,
    readSelected,
} from '@/routes/notifications';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Props = {
    notifications: NotificationRow[];
    unreadCount: number;
};

/**
 * The notification centre, shared by both modules.
 *
 * A list rather than only a dropdown: these rows are the record of what
 * happened to a posting or a contract, and the bell's menu shows five of them.
 * "See all" lands here, where the whole record can be read, ticked and cleared
 * — which is why the menu was added rather than this page replaced.
 */
export default function Notifications({ notifications, unreadCount }: Props) {
    const team = useCurrentTeam();
    const [selected, setSelected] = useState<string[]>([]);

    const allShown = notifications.map((row) => row.id);
    const everySelected =
        allShown.length > 0 && selected.length === allShown.length;

    const toggle = (id: string) => {
        setSelected((was) =>
            was.includes(id)
                ? was.filter((other) => other !== id)
                : [...was, id],
        );
    };

    /*
     * Selection is cleared by every action: the rows it named have either just
     * been deleted or just changed state, so keeping the ticks would leave the
     * next click aimed at ids that no longer mean what they did.
     */
    const submit = (method: 'post' | 'delete', url: string, withIds = true) => {
        /*
         * visit() rather than router.post()/router.delete(): the two do not
         * take the same arguments — delete has no data parameter — and a
         * shared helper that switches on the verb needs one shape for both.
         */
        router.visit(url, {
            method,
            data: withIds ? { ids: selected } : {},
            preserveScroll: true,
            onSuccess: () => setSelected([]),
        });
    };

    return (
        <>
            <Head title="Notifications" />

            <div
                style={{
                    maxWidth: 'clamp(1320px, 100vw - 320px, 1600px)',
                    margin: '0 auto',
                    padding: '30px clamp(16px, 4vw, 32px) 72px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 18,
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 16,
                        flexWrap: 'wrap',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                        }}
                    >
                        <h3 style={{ margin: 0 }}>Notifications</h3>
                        <span
                            style={{
                                fontSize: 12,
                                fontWeight: 600,
                                padding: '3px 10px',
                                borderRadius: 999,
                                color: 'var(--color-accent)',
                                background:
                                    'color-mix(in srgb, var(--color-accent) 16%, transparent)',
                            }}
                        >
                            {notifications.length}
                        </span>
                        {unreadCount > 0 && (
                            <span style={{ fontSize: 13, color: MUTED(60) }}>
                                {unreadCount} unread
                            </span>
                        )}
                    </div>

                    <div style={{ display: 'flex', gap: 8 }}>
                        {unreadCount > 0 && (
                            <Btn
                                variant="secondary"
                                onClick={() =>
                                    submit(
                                        'post',
                                        readAll.url(team.slug),
                                        false,
                                    )
                                }
                            >
                                Mark all read
                            </Btn>
                        )}

                        {/*
                         * Clear takes the read rows only. Unread ones are what
                         * nobody has looked at yet, and a single button that
                         * throws those away cannot be undone.
                         */}
                        <Btn
                            variant="ghost"
                            onClick={() =>
                                submit('delete', clear.url(team.slug), false)
                            }
                        >
                            <TrashIcon size={15} /> Clear read
                        </Btn>
                    </div>
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
                    <Panel padding="none" gap="none">
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                padding: '12px 14px',
                                borderBottom: `1px solid ${MUTED(12)}`,
                            }}
                        >
                            <Btn
                                variant="secondary"
                                disabled={selected.length === 0}
                                onClick={() =>
                                    submit('post', readSelected.url(team.slug))
                                }
                            >
                                <CheckIcon size={15} /> Mark as read
                            </Btn>

                            <Btn
                                variant="ghost"
                                disabled={selected.length === 0}
                                onClick={() =>
                                    submit('delete', destroy.url(team.slug))
                                }
                            >
                                <TrashIcon size={15} /> Delete
                            </Btn>

                            {selected.length > 0 && (
                                <span
                                    style={{
                                        fontSize: 12.5,
                                        color: MUTED(60),
                                    }}
                                >
                                    {selected.length} selected
                                </span>
                            )}
                        </div>

                        {/*
                         * A five-column table cannot shrink to a phone, so it
                         * scrolls inside its own box rather than pushing the
                         * page sideways. See .ai/rules/js.md.
                         */}
                        <div className="table-wrap">
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    fontSize: 13,
                                }}
                            >
                                <thead>
                                    <tr style={{ textAlign: 'left' }}>
                                        <Th style={{ width: 40 }}>
                                            <input
                                                type="checkbox"
                                                aria-label="Select every notification"
                                                checked={everySelected}
                                                onChange={() =>
                                                    setSelected(
                                                        everySelected
                                                            ? []
                                                            : allShown,
                                                    )
                                                }
                                            />
                                        </Th>
                                        <Th>From</Th>
                                        <Th>Subject</Th>
                                        <Th style={{ width: 110 }}>Sent</Th>
                                        <Th style={{ width: 70 }}>Read?</Th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {notifications.map((row) => (
                                        <tr
                                            key={row.id}
                                            style={{
                                                borderTop: `1px solid ${MUTED(10)}`,
                                                background: row.read
                                                    ? undefined
                                                    : 'color-mix(in srgb, var(--color-accent) 5%, transparent)',
                                            }}
                                        >
                                            <Td>
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Select the notification from ${row.from}`}
                                                    checked={selected.includes(
                                                        row.id,
                                                    )}
                                                    onChange={() =>
                                                        toggle(row.id)
                                                    }
                                                />
                                            </Td>

                                            <Td>
                                                <span
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 9,
                                                    }}
                                                >
                                                    <Avatar
                                                        name={row.from}
                                                        initials={row.initials}
                                                        avatarUrl={
                                                            row.avatarUrl
                                                        }
                                                        size={26}
                                                    />
                                                    <span
                                                        style={{
                                                            color: 'var(--color-accent)',
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        {row.from}
                                                    </span>
                                                </span>
                                            </Td>

                                            <Td>
                                                <Subject
                                                    row={row}
                                                    teamSlug={team.slug}
                                                />
                                            </Td>

                                            <Td
                                                style={{
                                                    color: MUTED(58),
                                                    fontSize: 12,
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                <div>{row.sentOn}</div>
                                                <div>{row.sentTime}</div>
                                            </Td>

                                            <Td>
                                                {row.read ? (
                                                    <CheckIcon
                                                        size={16}
                                                        weight="bold"
                                                        color="var(--color-accent)"
                                                        aria-label="Read"
                                                    />
                                                ) : (
                                                    <span
                                                        style={{
                                                            fontSize: 12,
                                                            color: MUTED(45),
                                                        }}
                                                    >
                                                        Unread
                                                    </span>
                                                )}
                                            </Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Panel>
                )}
            </div>
        </>
    );
}

/**
 * The subject cell.
 *
 * Rows that lead somewhere post rather than link, because opening one is what
 * marks it read — the controller carries it on to the subject afterwards.
 * Inertia renders a POST link as a <button>, so it is reset back to reading as
 * text. Rows with nowhere to go are plain text.
 */
function Subject({
    row,
    teamSlug,
}: {
    row: NotificationRow;
    teamSlug: string;
}) {
    const label = (
        <>
            <span>{row.title}</span>
            {row.body && (
                <span
                    style={{
                        display: 'block',
                        fontSize: 12,
                        color: MUTED(58),
                        marginTop: 2,
                    }}
                >
                    {row.body}
                </span>
            )}
        </>
    );

    if (row.url === null) {
        return <span>{label}</span>;
    }

    return (
        <Link
            href={read.url({ current_team: teamSlug, notification: row.id })}
            method="post"
            data={{ follow: true }}
            style={{
                font: 'inherit',
                textAlign: 'left',
                background: 'none',
                border: 0,
                padding: 0,
                cursor: 'pointer',
                color: 'var(--color-accent)',
            }}
        >
            {label}
        </Link>
    );
}

function Th({
    children,
    style,
}: {
    children?: React.ReactNode;
    style?: React.CSSProperties;
}) {
    return (
        <th
            style={{
                padding: '9px 14px',
                fontSize: 11.5,
                fontWeight: 600,
                letterSpacing: '.06em',
                textTransform: 'uppercase',
                color: MUTED(52),
                ...style,
            }}
        >
            {children}
        </th>
    );
}

function Td({
    children,
    style,
}: {
    children?: React.ReactNode;
    style?: React.CSSProperties;
}) {
    return (
        <td style={{ padding: '11px 14px', verticalAlign: 'top', ...style }}>
            {children}
        </td>
    );
}
