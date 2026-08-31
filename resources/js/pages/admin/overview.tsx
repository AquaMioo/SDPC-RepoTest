import { Head, usePage } from '@inertiajs/react';

import type { AdminStats } from '@/types/admin';

type RecentUser = { id: number; name: string; roleLabel: string };

type Props = {
    stats: AdminStats;
    recentUsers: RecentUser[];
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * The admin "Platform health" screen.
 *
 * Every number here is read from AdminStatistics — the design's sample figures
 * (248 users, 31 collaborations) are placeholders and are not reproduced.
 */
export default function AdminOverview({ stats, recentUsers }: Props) {
    const { props } = usePage<{ auth?: { user?: { name: string } | null } }>();
    const name = props.auth?.user?.name ?? '';

    return (
        <div
            style={{
                maxWidth: 'clamp(1180px, 100vw - 320px, 1600px)',
                margin: '0 auto',
                padding: '30px clamp(16px, 4vw, 32px) 72px',
            }}
        >
            <Head title="Admin overview" />

            <h3 style={{ margin: 0 }}>Welcome back,</h3>
            <div style={{ fontSize: 15, color: MUTED(55), marginBottom: 24 }}>
                admin · {name}
            </div>

            <h6 style={{ margin: '0 0 12px' }}>Platform health</h6>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                    gap: 16,
                    marginBottom: 20,
                }}
            >
                <StatCard
                    label="Total users"
                    value={stats.totalUsers}
                    caption="Registered accounts"
                />
                <StatCard
                    label="Approved"
                    value={stats.byStatus.approved ?? 0}
                    caption={`${stats.approvedPercentage}% of all accounts`}
                />
                <StatCard
                    label="Pending review"
                    value={stats.pendingReview}
                    caption="Awaiting a decision"
                />
                <StatCard
                    label="Deactivated"
                    value={stats.deactivated}
                    caption="Locked out of sign in"
                />
            </div>

            <div
                className="stack"
                style={{
                    ['--cols' as string]: '1.3fr 1fr',
                    gap: 16,
                }}
            >
                <div className="card elev-sm" style={{ padding: 20, gap: 14 }}>
                    <h6 style={{ margin: 0 }}>Accounts by role</h6>
                    {Object.entries(stats.byRole).map(([role, count]) => (
                        <Bar
                            key={role}
                            label={role}
                            value={count}
                            total={stats.totalUsers}
                        />
                    ))}
                </div>

                <div className="card elev-sm" style={{ padding: 20, gap: 12 }}>
                    <h6 style={{ margin: 0 }}>Newest accounts</h6>
                    {recentUsers.length === 0 && (
                        <div style={{ fontSize: 12.5, color: MUTED(55) }}>
                            No student or client accounts yet.
                        </div>
                    )}
                    {recentUsers.map((user) => (
                        <div
                            key={user.id}
                            style={{
                                padding: '10px 12px',
                                borderRadius: 'var(--radius-md)',
                                background: MUTED(5),
                                fontSize: 12.5,
                                lineHeight: 1.5,
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                            }}
                        >
                            <span style={{ marginRight: 'auto' }}>
                                {user.name}
                            </span>
                            <span className="tag tag-neutral">
                                {user.roleLabel}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function StatCard({
    label,
    value,
    caption,
}: {
    label: string;
    value: number;
    caption: string;
}) {
    return (
        <div className="card elev-sm" style={{ padding: 16, gap: 4 }}>
            <span style={{ fontSize: 12, color: MUTED(55) }}>{label}</span>
            <span
                style={{
                    fontFamily: 'var(--font-heading)',
                    fontSize: 30,
                    lineHeight: 1.1,
                }}
            >
                {value}
            </span>
            <span style={{ fontSize: 11, color: MUTED(45) }}>{caption}</span>
        </div>
    );
}

function Bar({
    label,
    value,
    total,
}: {
    label: string;
    value: number;
    total: number;
}) {
    const pct = total > 0 ? Math.round((value / total) * 100) : 0;

    return (
        <div>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    fontSize: 12.5,
                    marginBottom: 5,
                    textTransform: 'capitalize',
                }}
            >
                <span>{label}</span>
                <span style={{ color: 'var(--color-accent)' }}>{value}</span>
            </div>
            <div
                role="progressbar"
                aria-label={label}
                aria-valuenow={pct}
                aria-valuemin={0}
                aria-valuemax={100}
                style={{
                    height: 6,
                    borderRadius: 3,
                    background: 'var(--color-neutral-800)',
                }}
            >
                <div
                    style={{
                        width: `${pct}%`,
                        height: '100%',
                        borderRadius: 3,
                        background: 'var(--color-accent)',
                    }}
                />
            </div>
        </div>
    );
}
