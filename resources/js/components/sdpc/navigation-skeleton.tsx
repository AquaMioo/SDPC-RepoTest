import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * The placeholder shown while a page is on its way.
 *
 * Inertia keeps the screen you are leaving on display until the next one has
 * arrived, which reads as a dead click on anything slower than instant. This
 * puts the shape of the next page there instead.
 *
 * It waits before appearing. Most visits here land in about 150ms, and a
 * placeholder that flashes up and vanishes inside that is worse than none at
 * all — the screen looks like it glitched rather than loaded. Below the
 * threshold nothing is drawn and Inertia's progress bar carries it alone.
 */

/** How long a visit may take before it is worth showing anything. */
const THRESHOLD_MS = 300;

/** Roughly the sticky header, which the layout keeps drawn underneath. */
const HEADER_OFFSET = 62;

export default function NavigationSkeleton() {
    const [pending, setPending] = useState(false);

    useEffect(() => {
        let timer = 0;

        const start = router.on('start', (event) => {
            /*
             * Only whole navigations. A deferred panel arriving and a poll
             * refreshing a thread are both visits as far as the router is
             * concerned, and both carry `only`: covering the screen for those
             * would drape a placeholder over a page that is already drawn and
             * being read.
             */
            if ((event.detail.visit.only?.length ?? 0) > 0) {
                return;
            }

            timer = window.setTimeout(() => setPending(true), THRESHOLD_MS);
        });

        const settle = () => {
            clearTimeout(timer);
            setPending(false);
        };

        /*
         * finish covers arrival, cancellation and failure alike. Listening
         * only for success would strand the placeholder on screen whenever a
         * visit was cancelled by the next click.
         */
        const finish = router.on('finish', settle);

        return () => {
            clearTimeout(timer);
            start();
            finish();
        };
    }, []);

    if (!pending) {
        return null;
    }

    return (
        <div
            aria-hidden="true"
            style={{
                position: 'fixed',
                top: HEADER_OFFSET,
                left: 0,
                right: 0,
                bottom: 0,
                zIndex: 15,
                background: 'var(--color-bg)',
                overflow: 'hidden',
                pointerEvents: 'none',
            }}
        >
            <div
                className="page-shell"
                style={{
                    maxWidth: 1320,
                    paddingTop: 30,
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 22,
                }}
            >
                <Block width={220} height={26} />
                <Block width={320} height={14} />

                <div style={{ display: 'flex', gap: 22, flexWrap: 'wrap' }}>
                    <Block grow height={240} />
                    <Block grow height={240} />
                    <Block grow height={240} />
                </div>

                <Block height={96} />
            </div>
        </div>
    );
}

/**
 * One grey shape. Tinted from the text colour rather than a fixed grey, so it
 * sits correctly on the light user palette and the dark admin one alike.
 */
function Block({
    width,
    height,
    grow = false,
}: {
    width?: number;
    height: number;
    grow?: boolean;
}) {
    return (
        <span
            className="boot-block"
            style={{
                display: 'block',
                height,
                ...(grow
                    ? { flex: '1 1 260px' }
                    : { width: width ?? '100%', maxWidth: '100%' }),
                borderRadius: 8,
                background:
                    'color-mix(in srgb, var(--color-text) 7%, transparent)',
            }}
        />
    );
}
