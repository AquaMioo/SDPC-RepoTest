import { router } from '@inertiajs/react';

import { Btn } from '@/components/sdpc/btn';
import { Tag } from '@/components/sdpc/tag';
import { update } from '@/routes/admin/postings';
import type { AdminPosting } from '@/types/admin';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/** Which tag colour each posting status wears. */
const STATUS_VARIANT: Record<string, 'accent' | 'neutral' | 'outline'> = {
    open: 'accent',
    in_progress: 'accent',
    pending_review: 'outline',
    completed: 'neutral',
    closed: 'neutral',
    archived: 'neutral',
};

/**
 * Posting review — the third queue beside permits and credentials.
 *
 * A client publishes into "pending review"; the student board only lists open
 * postings. This list is the step between the two, so anything still waiting
 * sorts to the top. It had a screen of its own until content management
 * absorbed it; the decision endpoint is unchanged.
 */
export default function PostingReviewList({
    postings,
}: {
    postings: AdminPosting[];
}) {
    const decide = (slug: string, status: 'open' | 'closed') =>
        router.patch(update.url(slug), { status }, { preserveScroll: true });

    const waiting = postings.filter((posting) => posting.awaitingDecision).length;

    return (
        <section style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div>
                <h6 style={{ margin: 0 }}>Postings</h6>
                <div style={{ fontSize: 12.5, color: MUTED(55) }}>
                    A posting reaches students only once it is approved here
                    {waiting > 0 ? ` — ${waiting} waiting` : ''}
                </div>
            </div>

            {postings.length === 0 && (
                <div
                    className="card elev-sm"
                    style={{ padding: 20, fontSize: 13, color: MUTED(55) }}
                    data-test="no-postings"
                >
                    No client has published a posting yet.
                </div>
            )}

            {postings.map((posting) => (
                <div
                    key={posting.slug}
                    className="card elev-sm"
                    style={{ padding: 18, gap: 10 }}
                    data-test="posting-row"
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'flex-start',
                            gap: 12,
                            flexWrap: 'wrap',
                        }}
                    >
                        <div style={{ marginRight: 'auto', minWidth: 0 }}>
                            <div style={{ fontWeight: 600 }}>{posting.title}</div>
                            <div style={{ fontSize: 12, color: MUTED(60) }}>
                                {posting.business}
                                {posting.city ? ` · ${posting.city}` : ''} ·{' '}
                                {posting.category}
                                {posting.publishedAt
                                    ? ` · ${posting.publishedAt}`
                                    : ''}
                            </div>
                        </div>

                        <Tag
                            variant={STATUS_VARIANT[posting.status] ?? 'neutral'}
                        >
                            {posting.statusLabel}
                        </Tag>
                    </div>

                    <p
                        style={{
                            margin: 0,
                            fontSize: 12.5,
                            lineHeight: 1.55,
                            color: MUTED(72),
                        }}
                    >
                        {posting.description}
                    </p>

                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            flexWrap: 'wrap',
                            fontSize: 11.5,
                            color: MUTED(60),
                        }}
                    >
                        {posting.skills.map((skill) => (
                            <Tag key={skill} variant="outline">
                                {skill}
                            </Tag>
                        ))}
                    </div>

                    <div style={{ display: 'flex', gap: 8, marginTop: 2 }}>
                        {posting.status !== 'open' && (
                            <Btn
                                variant="primary"
                                onClick={() => decide(posting.slug, 'open')}
                            >
                                {posting.awaitingDecision
                                    ? 'Approve'
                                    : 'Reopen'}
                            </Btn>
                        )}
                        {posting.status !== 'closed' && (
                            <Btn
                                variant="ghost"
                                onClick={() => decide(posting.slug, 'closed')}
                            >
                                Close
                            </Btn>
                        )}
                    </div>
                </div>
            ))}
        </section>
    );
}
