import { useCallback, useEffect, useRef, useState } from 'react';

/** How many column panels sweep the screen. */
const PANELS = 5;

/** Milliseconds one panel takes to cross. */
const SLIDE_MS = 420;

/** Milliseconds between one panel starting and the next. */
const STAGGER_MS = 55;

/** The weighted snap the effect is drawn around. */
const EASING = 'cubic-bezier(0.76, 0, 0.24, 1)';

/** One half of the sweep: the last panel starts last and still has to finish. */
const HALF_MS = SLIDE_MS + (PANELS - 1) * STAGGER_MS;

export type Phase = 'idle' | 'covering' | 'revealing';
export type Direction = 'forward' | 'backward';

/**
 * Drive a directional panel sweep between two views.
 *
 * Returns the phase to render with, and a `go` that starts a sweep. The
 * caller swaps its own content when `onCovered` fires — the point of the
 * effect is that the change happens behind the panels, never in front of
 * somebody.
 *
 * Honours prefers-reduced-motion by swapping instantly and never mounting the
 * overlay: a full-screen shutter is exactly the kind of motion that setting
 * exists to refuse.
 */
export function useRoleTransition(onCovered: (next: string) => void) {
    const [phase, setPhase] = useState<Phase>('idle');
    const [direction, setDirection] = useState<Direction>('forward');
    const timers = useRef<number[]>([]);

    /* A sweep left running after unmount would setState on a dead component. */
    useEffect(
        () => () => {
            timers.current.forEach(clearTimeout);
        },
        [],
    );

    const go = useCallback(
        (next: string, isForward: boolean) => {
            const reduced = window.matchMedia(
                '(prefers-reduced-motion: reduce)',
            ).matches;

            if (reduced) {
                onCovered(next);

                return;
            }

            setDirection(isForward ? 'forward' : 'backward');
            setPhase('covering');

            /*
             * Timers rather than transitionend. A transition that never runs —
             * a background tab, a browser that skipped the frame — never fires
             * the event, and the overlay would then sit over the page forever
             * with nothing to clear it. A timer always comes back.
             */
            timers.current.push(
                window.setTimeout(() => {
                    onCovered(next);
                    setPhase('revealing');
                }, HALF_MS),
                window.setTimeout(() => setPhase('idle'), HALF_MS * 2),
            );
        },
        [onCovered],
    );

    return { phase, direction, go, busy: phase !== 'idle' };
}

/**
 * The shutter itself: five vertical columns over the whole screen.
 *
 * Forward sweeps in from the right edge and leaves to the left; backward
 * mirrors it. The column nearest the entering edge moves first, so the sweep
 * reads as one gesture crossing the screen rather than five things starting
 * at once.
 */
export default function RoleTransition({
    phase,
    direction,
}: {
    phase: Phase;
    direction: Direction;
}) {
    if (phase === 'idle') {
        return null;
    }

    const forward = direction === 'forward';

    /*
     * Which way this half of the sweep runs. Forward enters from the right
     * and leaves to the left; backward mirrors it.
     */
    const animation =
        phase === 'covering'
            ? forward
                ? 'sweep-in-from-right'
                : 'sweep-in-from-left'
            : forward
              ? 'sweep-out-to-left'
              : 'sweep-out-to-right';

    return (
        <div className="sweep" aria-hidden>
            {Array.from({ length: PANELS }, (_, index) => (
                <div
                    key={index}
                    className="sweep-col"
                    style={{
                        left: `${(index * 100) / PANELS}%`,
                        /*
                         * A hair over a fifth: at fractional viewport widths
                         * exact fifths leave hairline gaps and the page
                         * flickers through between the columns.
                         */
                        width: `calc(${100 / PANELS}% + 1px)`,
                        animationName: animation,
                        /*
                         * The column nearest the entering edge moves first, so
                         * the sweep reads as one gesture crossing the screen
                         * rather than five things starting at once.
                         */
                        animationDelay: `${(forward ? PANELS - 1 - index : index) * STAGGER_MS}ms`,
                    }}
                />
            ))}
        </div>
    );
}

/**
 * A line of copy that slides up into view as the panels clear.
 *
 * The mask is the parent's overflow: the text starts pushed below its own box
 * and rises into it, so it appears from behind an edge rather than fading in
 * on the spot.
 */
export function Reveal({
    phase,
    delay = 0,
    children,
}: {
    phase: Phase;
    delay?: number;
    children: React.ReactNode;
}) {
    const hidden = phase === 'covering';

    return (
        <span style={{ display: 'block', overflow: 'hidden' }}>
            <span
                style={{
                    display: 'block',
                    transform: hidden ? 'translateY(105%)' : 'translateY(0)',
                    transition: `transform ${SLIDE_MS}ms ${EASING} ${delay}ms`,
                }}
            >
                {children}
            </span>
        </span>
    );
}
