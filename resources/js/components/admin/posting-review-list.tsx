import { router } from '@inertiajs/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Textarea } from '@/components/sdpc/input';
import { PageNumbers, usePagination } from '@/components/sdpc/page-numbers';
import { Tag } from '@/components/sdpc/tag';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy, update } from '@/routes/admin/postings';
import type { AdminPosting } from '@/types/admin';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/** How many postings the dashboard shows at once. */
const POSTINGS_PER_PAGE = 5;

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

    /* Remove asks why first: the reason is what the client is told. */
    const [toRemove, setToRemove] = useState<AdminPosting | null>(null);
    const [reason, setReason] = useState('');
    const [reasonError, setReasonError] = useState<string | undefined>();
    const [removing, setRemoving] = useState(false);

    const openRemove = (posting: AdminPosting) => {
        setToRemove(posting);
        setReason('');
        setReasonError(undefined);
    };

    const confirmRemove = () => {
        if (toRemove === null) {
            return;
        }

        router.delete(destroy.url(toRemove.slug), {
            data: { reason },
            preserveScroll: true,
            onStart: () => setRemoving(true),
            onSuccess: () => setToRemove(null),
            onError: (errors) => setReasonError(errors.reason),
            onFinish: () => setRemoving(false),
        });
    };

    const waiting = postings.filter(
        (posting) => posting.awaitingDecision,
    ).length;

    /* Five to a page, paged in place: no request and no reload. */
    const { page, pageCount, setPage, pageItems } = usePagination(
        postings,
        POSTINGS_PER_PAGE,
    );

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

            {pageItems.map((posting) => (
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
                            <div style={{ fontWeight: 600 }}>
                                {posting.title}
                            </div>
                            <div style={{ fontSize: 12, color: MUTED(60) }}>
                                {posting.business}
                                {posting.city
                                    ? ` · ${posting.city}`
                                    : ''} · {posting.category}
                                {posting.publishedAt
                                    ? ` · ${posting.publishedAt}`
                                    : ''}
                            </div>
                        </div>

                        <Tag
                            variant={
                                STATUS_VARIANT[posting.status] ?? 'neutral'
                            }
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

                    {/* A build under way, delivered or withdrawn is not the
                        queue's to reopen or close: doing so left a running
                        project unable to be completed. */}
                    {posting.isModeratable && (
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
                            <Btn
                                variant="ghost"
                                data-test="remove-posting"
                                onClick={() => openRemove(posting)}
                            >
                                Remove
                            </Btn>
                        </div>
                    )}
                </div>
            ))}

            <PageNumbers
                page={page}
                pageCount={pageCount}
                onChange={setPage}
                label="Postings"
            />

            <Dialog
                open={toRemove !== null}
                onOpenChange={(open) => {
                    if (!open && !removing) {
                        setToRemove(null);
                    }
                }}
            >
                <DialogContent data-test="remove-posting-dialog">
                    <DialogHeader>
                        <DialogTitle>Remove posting</DialogTitle>
                        <DialogDescription>
                            “{toRemove?.title}” will be deleted and taken off
                            the board. The client is notified with your reason.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="field">
                        <label htmlFor="remove-reason">
                            Why is this posting being removed?
                        </label>
                        <Textarea
                            id="remove-reason"
                            value={reason}
                            maxLength={2000}
                            autoFocus
                            onChange={(event) => setReason(event.target.value)}
                            aria-invalid={Boolean(reasonError)}
                        />
                        <InputError
                            message={reasonError}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <DialogFooter className="gap-3">
                        <DialogClose asChild>
                            <Button variant="secondary" disabled={removing}>
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            data-test="confirm-remove-posting"
                            disabled={removing || reason.trim() === ''}
                            onClick={confirmRemove}
                        >
                            Remove
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
