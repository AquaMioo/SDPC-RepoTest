import { Head, Link } from '@inertiajs/react';

import { index as adminUsers } from '@/routes/admin/users';

type Account = {
    id: number;
    name: string;
    avatarUrl: string | null;
    email: string;
    roleLabel: string;
    since: string | null;
    openReports: number;
};

type Props = {
    accounts: Account[];
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Accounts an administrator has put under watch.
 *
 * Monitoring is not a punishment and not an approval — it is the middle state
 * for an account worth keeping an eye on. Nothing on the platform is denied to
 * these accounts; this screen exists so the status is something an
 * administrator can come back to rather than a label set once and forgotten.
 */
export default function AdminMonitoring({ accounts }: Props) {
    return (
        <div
            style={{ maxWidth: 1180, margin: '0 auto', padding: '30px 32px 72px' }}
        >
            <Head title="Monitoring" />

            <div style={{ marginBottom: 20 }}>
                <h3 style={{ margin: 0 }}>Monitoring</h3>
                <div style={{ fontSize: 13, color: MUTED(55) }}>
                    Accounts being kept an eye on
                </div>
            </div>

            {accounts.length === 0 ? (
                <p
                    style={{
                        padding: '11px 13px',
                        borderRadius: 'var(--radius-md)',
                        background: MUTED(5),
                        fontSize: 12,
                        lineHeight: 1.6,
                        color: MUTED(65),
                    }}
                >
                    <b style={{ color: 'var(--color-text)' }}>Nobody is being monitored.</b>{' '}
                    Set an account to Monitored on the{' '}
                    <Link href={adminUsers.url()}>Users</Link> page and it will
                    appear here.
                </p>
            ) : (
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 10,
                    }}
                >
                    {accounts.map((account) => (
                        <div
                            key={account.id}
                            className="card elev-sm"
                            style={{
                                padding: '14px 18px',
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                            }}
                        >
                            <span
                                style={{
                                    display: 'grid',
                                    placeItems: 'center',
                                    width: 34,
                                    height: 34,
                                    borderRadius: '50%',
                                    overflow: 'hidden',
                                    flex: 'none',
                                    background: 'var(--color-accent-800)',
                                    color: 'var(--color-accent-200)',
                                    fontSize: 13,
                                }}
                            >
                                {account.avatarUrl ? (
                                    <img
                                        src={account.avatarUrl}
                                        alt=""
                                        style={{
                                            width: '100%',
                                            height: '100%',
                                            objectFit: 'cover',
                                        }}
                                    />
                                ) : (
                                    account.name.charAt(0).toUpperCase()
                                )}
                            </span>

                            <div style={{ minWidth: 0, marginRight: 'auto' }}>
                                <div style={{ fontSize: 14 }}>{account.name}</div>
                                <div
                                    style={{ fontSize: 11.5, color: MUTED(50) }}
                                >
                                    {account.email} · {account.roleLabel}
                                    {account.since
                                        ? ` · since ${account.since}`
                                        : ''}
                                </div>
                            </div>

                            {/* The usual reason an account is here. */}
                            {account.openReports > 0 && (
                                <span className="tag tag-neutral">
                                    {account.openReports} open report
                                    {account.openReports === 1 ? '' : 's'}
                                </span>
                            )}

                            <Link
                                href={adminUsers.url()}
                                style={{ fontSize: 12.5 }}
                            >
                                Change status
                            </Link>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
