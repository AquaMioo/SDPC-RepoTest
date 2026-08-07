import { Head, Link } from '@inertiajs/react';

import { Btn } from '@/components/sdpc/btn';
import { overview } from '@/routes/admin';
import { index as adminUsers } from '@/routes/admin/users';
import type { AdminStats } from '@/types/admin';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * The admin landing screen after sign in — a condensed read of platform health
 * with a way through to the full overview.
 */
export default function AdminDashboard({ stats }: { stats: AdminStats }) {
    return (
        <div style={{ maxWidth: 1180, margin: '0 auto', padding: '30px 32px 72px' }}>
            <Head title="Admin dashboard" />

            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-end',
                    gap: 16,
                    marginBottom: 24,
                }}
            >
                <div style={{ marginRight: 'auto' }}>
                    <h3 style={{ margin: 0 }}>Dashboard overview</h3>
                    <div style={{ fontSize: 13, color: MUTED(55) }}>
                        Accounts, review queue and platform totals
                    </div>
                </div>

                <Btn asChild variant="secondary">
                    <Link href={overview.url()}>Full overview</Link>
                </Btn>
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(4,1fr)',
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

            {stats.pendingReview > 0 && (
                <div
                    className="card elev-sm"
                    style={{
                        padding: 20,
                        gap: 12,
                        flexDirection: 'row',
                        alignItems: 'center',
                    }}
                >
                    <div style={{ marginRight: 'auto', fontSize: 13.5 }}>
                        {stats.pendingReview} account
                        {stats.pendingReview === 1 ? '' : 's'} waiting on review.
                    </div>
                    <Btn asChild variant="primary">
                        <Link href={adminUsers.url()}>Review accounts</Link>
                    </Btn>
                </div>
            )}
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
