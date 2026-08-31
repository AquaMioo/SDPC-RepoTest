import { Head, router } from '@inertiajs/react';
import { MagnifyingGlassIcon } from '@phosphor-icons/react';
import { useMemo, useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { Input } from '@/components/sdpc/input';
import { update as updateUserStatus } from '@/routes/admin/users/status';
import type { AdminUserRow, StatusOption, UserStatus } from '@/types/admin';

type Props = {
    users: AdminUserRow[];
    statuses: StatusOption[];
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/** The tag colour the design gives each account state. */
const STATUS_TAG: Record<UserStatus, string> = {
    approved: 'tag tag-accent',
    pending: 'tag tag-outline',
    monitored: 'tag tag-accent-2',
    deactivated: 'tag tag-neutral',
};

export default function AdminUsers({ users, statuses }: Props) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (!needle) {
            return users;
        }

        return users.filter(
            (user) =>
                user.name.toLowerCase().includes(needle) ||
                user.email.toLowerCase().includes(needle),
        );
    }, [users, query]);

    return (
        <div
            style={{
                maxWidth: 'clamp(1180px, 100vw - 320px, 1600px)',
                margin: '0 auto',
                padding: '30px clamp(16px, 4vw, 32px) 72px',
            }}
        >
            <Head title="User account management" />

            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-end',
                    gap: 16,
                    marginBottom: 20,
                }}
            >
                <div style={{ marginRight: 'auto' }}>
                    <h3 style={{ margin: 0 }}>User account management</h3>
                    <div style={{ fontSize: 13, color: MUTED(55) }}>
                        Approve, monitor and deactivate accounts
                    </div>
                </div>

                <div style={{ position: 'relative', width: 240 }}>
                    <MagnifyingGlassIcon
                        style={{
                            position: 'absolute',
                            left: 10,
                            top: 9,
                            fontSize: 15,
                            opacity: 0.45,
                        }}
                    />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search name or email"
                        aria-label="Search users"
                        style={{ paddingLeft: 31 }}
                    />
                </div>
            </div>

            <div className="card elev-sm" style={{ padding: '14px 6px' }}>
                {/* The roster keeps its columns and scrolls; squeezed into a
                    phone the cells wrap into something unreadable. */}
                <div className="table-wrap">
                    <table className="table">
                        <thead>
                            <tr>
                                <th style={{ paddingLeft: 16 }}>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th
                                    style={{
                                        textAlign: 'right',
                                        paddingRight: 16,
                                    }}
                                >
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        style={{
                                            paddingLeft: 16,
                                            color: MUTED(55),
                                            fontSize: 13,
                                        }}
                                    >
                                        No accounts match “{query}”.
                                    </td>
                                </tr>
                            )}

                            {filtered.map((user) => (
                                <tr key={user.id}>
                                    <td style={{ paddingLeft: 16 }}>
                                        <span
                                            style={{
                                                display: 'inline-grid',
                                                placeItems: 'center',
                                                width: 26,
                                                height: 26,
                                                borderRadius: '50%',
                                                overflow: 'hidden',
                                                marginRight: 8,
                                                verticalAlign: 'middle',
                                                background:
                                                    'var(--color-accent-800)',
                                                color: 'var(--color-accent-200)',
                                                fontSize: 11,
                                            }}
                                        >
                                            {user.avatarUrl ? (
                                                <img
                                                    src={user.avatarUrl}
                                                    alt=""
                                                    style={{
                                                        width: '100%',
                                                        height: '100%',
                                                        objectFit: 'cover',
                                                    }}
                                                />
                                            ) : (
                                                user.name
                                                    .charAt(0)
                                                    .toUpperCase()
                                            )}
                                        </span>
                                        {user.name}
                                        {user.credentialStatusLabel && (
                                            <span
                                                className="tag tag-outline"
                                                style={{ marginLeft: 8 }}
                                            >
                                                {user.credentialStatusLabel}
                                            </span>
                                        )}
                                    </td>
                                    <td style={{ color: MUTED(65) }}>
                                        {user.email}
                                    </td>
                                    <td style={{ color: MUTED(65) }}>
                                        {user.roleLabel}
                                    </td>
                                    <td>
                                        <span
                                            className={STATUS_TAG[user.status]}
                                        >
                                            {statuses.find(
                                                (option) =>
                                                    option.value ===
                                                    user.status,
                                            )?.label ?? user.status}
                                        </span>
                                    </td>
                                    <td style={{ paddingRight: 16 }}>
                                        <div
                                            style={{
                                                display: 'flex',
                                                gap: 6,
                                                justifyContent: 'flex-end',
                                            }}
                                        >
                                            {/* Administrators cannot change their own
                                            status; the server refuses it too. */}
                                            {user.isSelf ? (
                                                <span
                                                    style={{
                                                        fontSize: 12,
                                                        color: MUTED(45),
                                                    }}
                                                >
                                                    This is you
                                                </span>
                                            ) : (
                                                /*
                                                 * Every state, every row, with the
                                                 * one in force highlighted — a set
                                                 * of choices where the current one
                                                 * is shown, not hidden.
                                                 *
                                                 * It used to drop the current
                                                 * status and paint "Approved"
                                                 * primary on every row, so each
                                                 * account looked approved whatever
                                                 * it actually was.
                                                 */
                                                statuses.map((option) => {
                                                    const isCurrent =
                                                        option.value ===
                                                        user.status;

                                                    return (
                                                        <Btn
                                                            key={option.value}
                                                            variant={
                                                                isCurrent
                                                                    ? 'primary'
                                                                    : 'ghost'
                                                            }
                                                            aria-pressed={
                                                                isCurrent
                                                            }
                                                            disabled={isCurrent}
                                                            title={
                                                                isCurrent
                                                                    ? `Already ${option.label.toLowerCase()}`
                                                                    : `Set to ${option.label.toLowerCase()}`
                                                            }
                                                            style={{
                                                                fontSize: 12,
                                                                padding:
                                                                    '4px 10px',
                                                                // The unselected
                                                                // ones stay legible
                                                                // but recede.
                                                                opacity:
                                                                    isCurrent
                                                                        ? 1
                                                                        : 0.75,
                                                                border: isCurrent
                                                                    ? undefined
                                                                    : `1px solid ${MUTED(18)}`,
                                                            }}
                                                            onClick={() =>
                                                                router.patch(
                                                                    updateUserStatus.url(
                                                                        user.id,
                                                                    ),
                                                                    {
                                                                        status: option.value,
                                                                    },
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            {option.label}
                                                        </Btn>
                                                    );
                                                })
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
