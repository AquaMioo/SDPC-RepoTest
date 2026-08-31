import { Head, Link } from '@inertiajs/react';

import ContentEditor from '@/components/admin/content-editor';
import PostingReviewList from '@/components/admin/posting-review-list';
import { Btn } from '@/components/sdpc/btn';
import { overview } from '@/routes/admin';
import { index as adminUsers } from '@/routes/admin/users';
import type { AdminPosting, AdminStats, SiteContentDraft } from '@/types/admin';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const RULE: React.CSSProperties = {
    height: 1,
    background: 'var(--color-divider)',
    margin: '28px 0 24px',
};

type Props = {
    stats: AdminStats;
    postings: AdminPosting[];
    content?: Partial<Record<keyof SiteContentDraft, string | null>>;
};

/**
 * The dashboard overview — the administrator's one working screen.
 *
 * Postings review and content management used to be screens of their own. The
 * scope names neither, so both live here now: platform health first, then the
 * posting queue, then the copy. Their write endpoints are unchanged.
 */
export default function AdminDashboard({ stats, postings, content }: Props) {
    return (
        <div
            style={{
                maxWidth: 'clamp(1180px, 100vw - 320px, 1600px)',
                margin: '0 auto',
                padding: '30px clamp(16px, 4vw, 32px) 72px',
            }}
        >
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
                        {stats.pendingReview === 1 ? '' : 's'} waiting on
                        review.
                    </div>
                    <Btn asChild variant="primary">
                        <Link href={adminUsers.url()}>Review accounts</Link>
                    </Btn>
                </div>
            )}

            <div style={RULE} />

            <PostingReviewList postings={postings} />

            <div style={RULE} />

            <ContentEditor content={content} />
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
