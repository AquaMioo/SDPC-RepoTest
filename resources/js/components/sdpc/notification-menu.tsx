import { Link } from '@inertiajs/react';
import { BellIcon, CheckIcon, ListIcon } from '@phosphor-icons/react';
import { useEffect, useRef, useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { index as notificationsIndex, read } from '@/routes/notifications';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

export type NotificationRow = {
    id: string;
    from: string;
    initials: string;
    /**
     * The actor's picture, from User::avatarUrl(). Null when nobody triggered
     * the notification, or when the row predates the actor's id being stored.
     */
    avatarUrl: string | null;
    title: string;
    body: string | null;
    url: string | null;
    sentOn: string | null;
    sentTime: string | null;
    read: boolean;
};

/**
 * The bell's menu.
 *
 * The list at /notifications used to be the only surface, on the reasoning
 * that these rows are a record worth reading rather than something to bury in
 * a panel. The panel is back because the record is easier to keep when the
 * five newest are one click away — but "See all" at the foot is the point of
 * it, not an afterthought: the full list is still where the record lives.
 *
 * Rows are handed down from HandleInertiaRequests, so opening this costs no
 * request. Six arrive and five are drawn; the sixth is only how the footer
 * knows to say there is more.
 */
export function NotificationMenu({
    rows,
    unread,
    teamSlug,
}: {
    rows: NotificationRow[];
    unread: number;
    teamSlug: string;
}) {
    const [open, setOpen] = useState(false);
    const wrap = useRef<HTMLDivElement>(null);

    /*
     * Closing is bound to the document rather than to the panel's own blur:
     * the rows are links, and a blur handler fires before the click lands and
     * eats it. Escape is handled here too so the trigger keeps focus.
     */
    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: MouseEvent) => {
            if (!wrap.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    const visible = rows.slice(0, 5);

    return (
        <div ref={wrap} style={{ position: 'relative' }}>
            <Btn
                icon
                variant="bare"
                aria-label="Notifications"
                aria-expanded={open}
                aria-haspopup="menu"
                onClick={() => setOpen((was) => !was)}
                style={{ color: 'var(--color-text)', position: 'relative' }}
            >
                <BellIcon size={22} />
                {unread > 0 && (
                    <span
                        aria-hidden="true"
                        style={{
                            position: 'absolute',
                            top: 6,
                            right: 6,
                            width: 7,
                            height: 7,
                            borderRadius: '50%',
                            background: 'var(--color-accent)',
                        }}
                    />
                )}
            </Btn>

            {open && (
                <div
                    role="menu"
                    aria-label="Notifications"
                    style={{
                        position: 'absolute',
                        top: 'calc(100% + 8px)',
                        right: 0,
                        zIndex: 60,
                        width: 'min(390px, calc(100vw - 32px))',
                        background: 'var(--color-surface, var(--color-bg))',
                        border: `1px solid ${MUTED(14)}`,
                        borderRadius: 10,
                        boxShadow: '0 18px 40px rgba(0,0,0,.28)',
                        overflow: 'hidden',
                    }}
                >
                    {visible.length === 0 ? (
                        <div
                            style={{
                                padding: '22px 16px',
                                fontSize: 13,
                                color: MUTED(65),
                            }}
                        >
                            Nothing yet. Applications, approvals and signatures
                            land here as they happen.
                        </div>
                    ) : (
                        visible.map((row, index) => (
                            <Row
                                key={row.id}
                                row={row}
                                teamSlug={teamSlug}
                                first={index === 0}
                                onFollow={() => setOpen(false)}
                            />
                        ))
                    )}

                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '10px 14px',
                            borderTop: `1px solid ${MUTED(14)}`,
                        }}
                    >
                        <ListIcon size={16} color={MUTED(55)} />
                        <Link
                            href={notificationsIndex.url(teamSlug)}
                            onClick={() => setOpen(false)}
                            data-quiet=""
                            style={{
                                fontSize: 13,
                                textDecoration: 'none',
                            }}
                        >
                            See all
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * One row: who it is from, what happened, and when.
 */
function Row({
    row,
    teamSlug,
    first,
    onFollow,
}: {
    row: NotificationRow;
    teamSlug: string;
    first: boolean;
    onFollow: () => void;
}) {
    const openHref = read.url({
        current_team: teamSlug,
        notification: row.id,
    });

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'flex-start',
                gap: 10,
                padding: '11px 14px',
                borderTop: first ? undefined : `1px solid ${MUTED(10)}`,
            }}
        >
            <Avatar
                name={row.from}
                initials={row.initials}
                avatarUrl={row.avatarUrl}
            />

            <Link
                href={openHref}
                method="post"
                data={{ follow: true }}
                onClick={onFollow}
                style={{
                    flex: 1,
                    minWidth: 0,
                    font: 'inherit',
                    textAlign: 'left',
                    background: 'none',
                    border: 0,
                    padding: 0,
                    cursor: 'pointer',
                    color: 'inherit',
                }}
            >
                <div
                    style={{
                        fontSize: 13,
                        fontWeight: 600,
                        color: 'var(--color-accent)',
                    }}
                >
                    {row.from}
                </div>

                {/*
                 * One line only. These titles carry a project name and a
                 * person's name, so they run long — wrapping them turns a
                 * five-row menu into a page.
                 */}
                <div
                    style={{
                        fontSize: 12.5,
                        color: MUTED(72),
                        marginTop: 1,
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                    }}
                >
                    {row.title}
                </div>

                <div style={{ fontSize: 11.5, color: MUTED(48), marginTop: 3 }}>
                    {[row.sentOn, row.sentTime].filter(Boolean).join(', ')}
                </div>
            </Link>

            {/*
             * Marking read without following, which is why it posts without
             * `follow`. Drawn faintly once the row is already read so the
             * column keeps its width and the rows stay on one grid.
             */}
            <Link
                href={openHref}
                method="post"
                aria-label={row.read ? 'Already read' : 'Mark as read'}
                style={{
                    flexShrink: 0,
                    marginTop: 2,
                    background: 'none',
                    border: 0,
                    padding: 2,
                    cursor: row.read ? 'default' : 'pointer',
                    color: row.read ? MUTED(28) : 'var(--color-accent)',
                }}
            >
                <CheckIcon size={16} weight="bold" />
            </Link>
        </div>
    );
}

/**
 * The circle in front of a row.
 *
 * The actor's picture when the payload named an account to read one from,
 * and coloured initials when it did not — an event nobody triggered, or a row
 * written before the actor's id was stored beside their name. The hue is
 * derived from the name so one person keeps one colour across the list.
 */
export function Avatar({
    name,
    initials,
    avatarUrl = null,
    size = 30,
}: {
    name: string;
    initials: string;
    /** The actor's picture, when the payload named an account to read. */
    avatarUrl?: string | null;
    size?: number;
}) {
    if (avatarUrl !== null && avatarUrl !== '') {
        return (
            <img
                src={avatarUrl}
                alt=""
                title={name}
                style={{
                    flexShrink: 0,
                    width: size,
                    height: size,
                    borderRadius: '50%',
                    objectFit: 'cover',
                }}
            />
        );
    }

    let hash = 0;

    for (let i = 0; i < name.length; i++) {
        hash = (hash * 31 + name.charCodeAt(i)) % 360;
    }

    return (
        <span
            aria-hidden="true"
            style={{
                flexShrink: 0,
                width: size,
                height: size,
                borderRadius: '50%',
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: size * 0.38,
                fontWeight: 600,
                letterSpacing: '.02em',
                color: `hsl(${hash} 70% 88%)`,
                background: `hsl(${hash} 42% 34%)`,
            }}
        >
            {initials}
        </span>
    );
}
