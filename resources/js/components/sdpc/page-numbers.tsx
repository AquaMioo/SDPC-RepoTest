import { useState } from 'react';

/**
 * Page a list that is already on the screen.
 *
 * The admin lists arrive whole, so paging them is only a matter of which slice
 * to draw — no request and no page reload. The page is clamped, so a list
 * that shrinks under it (a filter, an approval) never leaves it on an empty
 * page.
 */
export function usePagination<T>(items: T[], perPage: number) {
    const [requestedPage, setPage] = useState(1);

    const pageCount = Math.max(1, Math.ceil(items.length / perPage));
    const page = Math.min(requestedPage, pageCount);

    return {
        page,
        pageCount,
        setPage,
        pageItems: items.slice((page - 1) * perPage, page * perPage),
    };
}

type Props = {
    page: number;
    pageCount: number;
    onChange: (page: number) => void;
    /** What is being paged, for the screen reader label. */
    label: string;
};

/**
 * Previous, the page numbers, next, in the design's button styles. Hidden when
 * everything fits on one page.
 */
export function PageNumbers({ page, pageCount, onChange, label }: Props) {
    if (pageCount <= 1) {
        return null;
    }

    return (
        <nav
            aria-label={`${label} pages`}
            style={{
                display: 'flex',
                flexWrap: 'wrap',
                gap: 6,
                marginTop: 16,
                justifyContent: 'center',
            }}
        >
            <button
                type="button"
                className="btn btn-ghost"
                disabled={page === 1}
                aria-label="Previous page"
                onClick={() => onChange(page - 1)}
            >
                ‹
            </button>

            {Array.from({ length: pageCount }, (_, index) => index + 1).map(
                (number) => (
                    <button
                        key={number}
                        type="button"
                        className={
                            number === page
                                ? 'btn btn-primary'
                                : 'btn btn-ghost'
                        }
                        aria-current={number === page ? 'page' : undefined}
                        onClick={() => onChange(number)}
                    >
                        {number}
                    </button>
                ),
            )}

            <button
                type="button"
                className="btn btn-ghost"
                disabled={page === pageCount}
                aria-label="Next page"
                onClick={() => onChange(page + 1)}
            >
                ›
            </button>
        </nav>
    );
}
