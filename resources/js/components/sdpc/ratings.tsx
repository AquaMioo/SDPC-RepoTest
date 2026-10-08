import { StarIcon } from '@phosphor-icons/react';
import { useState } from 'react';

import { Btn } from '@/components/sdpc/btn';

/** One client's rating of a student, as App\Models\ProjectRating::toReview() sends it. */
export type Review = {
    id: number;
    rating: number;
    feedback: string | null;
    clientName: string;
    projectTitle: string;
    ratedOn: string | null;
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/** How many reviews show before "Show all". */
const REVIEWS_SHOWN = 3;

/** Five stars, filled up to the rating. */
export function Stars({ value, size = 13 }: { value: number; size?: number }) {
    return (
        <span
            aria-hidden="true"
            style={{ display: 'inline-flex', gap: 1, color: '#e0a526' }}
        >
            {[1, 2, 3, 4, 5].map((star) => (
                <StarIcon
                    key={star}
                    size={size}
                    weight={value >= star - 0.25 ? 'fill' : 'regular'}
                />
            ))}
        </span>
    );
}

/**
 * The rating line under a student's name: stars, the average and how many
 * clients rated them — or "No ratings yet".
 */
export function RatingLine({
    rating,
    count,
}: {
    rating: number;
    count: number;
}) {
    return (
        <div
            data-test="rating-line"
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 6,
                fontSize: 12.5,
                color: MUTED(65),
            }}
        >
            {count > 0 ? (
                <>
                    <Stars value={rating} />
                    <span>
                        {rating.toFixed(1)} · {count}{' '}
                        {count === 1 ? 'review' : 'reviews'}
                    </span>
                </>
            ) : (
                <span>No ratings yet</span>
            )}
        </div>
    );
}

/**
 * Shown under the name of an account an administrator deactivated, wherever
 * other people see it (owner, 2026-10-09).
 */
export function DeactivatedTag() {
    return (
        <span
            data-test="deactivated-tag"
            className="tag"
            style={{
                alignSelf: 'flex-start',
                width: 'fit-content',
                background:
                    'color-mix(in srgb, var(--destructive) 14%, transparent)',
                color: 'var(--destructive)',
            }}
        >
            Account deactivated
        </span>
    );
}

/**
 * Clients' feedback on completed builds, newest first, with "Show all" once
 * there are more than a few.
 */
export function FeedbackList({ reviews }: { reviews: Review[] }) {
    const [showAll, setShowAll] = useState(false);

    if (reviews.length === 0) {
        return (
            <span style={{ fontSize: 12.5, color: MUTED(55) }}>
                No feedback yet.
            </span>
        );
    }

    const shown = showAll ? reviews : reviews.slice(0, REVIEWS_SHOWN);

    return (
        <div data-test="feedback-list" style={{ display: 'grid', gap: 10 }}>
            {shown.map((review) => (
                <div
                    key={review.id}
                    data-test="feedback-item"
                    style={{ display: 'grid', gap: 3, fontSize: 12.5 }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                        }}
                    >
                        <Stars value={review.rating} size={12} />
                        <span style={{ color: MUTED(55), fontSize: 11.5 }}>
                            {review.ratedOn}
                        </span>
                    </div>
                    {review.feedback && (
                        <span style={{ lineHeight: 1.5 }}>
                            “{review.feedback}”
                        </span>
                    )}
                    <span style={{ color: MUTED(55), fontSize: 11.5 }}>
                        {review.clientName} · {review.projectTitle}
                    </span>
                </div>
            ))}

            {reviews.length > REVIEWS_SHOWN && (
                <Btn
                    variant="ghost"
                    style={{ justifySelf: 'start' }}
                    onClick={() => setShowAll((value) => !value)}
                >
                    {showAll ? 'Show less' : `Show all (${reviews.length})`}
                </Btn>
            )}
        </div>
    );
}
