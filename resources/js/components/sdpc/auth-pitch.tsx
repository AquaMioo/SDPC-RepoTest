import { Link } from '@inertiajs/react';

import type { ReactNode } from 'react';
import { Reveal } from '@/components/sdpc/role-transition';
import type { Phase } from '@/components/sdpc/role-transition';

const MUTED = 'color-mix(in srgb, var(--color-text) 55%, transparent)';

/** The wordmark, in the pitch panel and its small-screen stand-in. */
export const wordmark: React.CSSProperties = {
    fontFamily: 'var(--font-heading)',
    fontWeight: 600,
    fontSize: 22,
    letterSpacing: '-0.02em',
    color: 'var(--color-accent)',
    textDecoration: 'none',
};

type Props = {
    /** The claim. Wrap the closing phrase in <Accent> to pick up the green. */
    headline: ReactNode;
    body: string;
    /** Which half of the split this sits in; decides the divider's edge. */
    side?: 'left' | 'right';
    /**
     * Drives the masked reveal. Omit on screens with no sweep — log in has
     * none — and the copy simply sits there.
     */
    phase?: Phase;
    /**
     * Extra classes for the aside ITSELF, never a wrapper around it.
     *
     * A wrapping div would become the grid item instead, leaving this element
     * sized to its content — and justify-between then has no spare height to
     * spread the wordmark, the headline and the proof row across.
     */
    className?: string;
};

/** The closing phrase of a headline, in the accent colour. */
export function Accent({ children }: { children: ReactNode }) {
    return <span style={{ color: 'var(--color-accent)' }}>{children}</span>;
}

/**
 * The pitch beside an auth form.
 *
 * Shared by log in and register rather than copied, because the whole point of
 * the split shell is that the two screens are one design — and a duplicated
 * type scale is a design that drifts the first time somebody adjusts a size on
 * only one of them.
 *
 * Hidden below `lg` rather than stacked above the form: on a phone the first
 * thing wanted is the field, not the tagline.
 *
 * The ticked "Verified students and businesses" / "N projects delivered" row
 * that used to sit along the foot is gone on purpose — see the polishing pass.
 * justify-between now spreads two children rather than three, which is what
 * puts the headline against the vertical middle.
 */
export default function AuthPitch({
    headline,
    body,
    side = 'left',
    className = '',
    phase = 'idle',
}: Props) {
    return (
        <aside
            className={
                'relative hidden flex-col p-12 lg:flex xl:p-16 ' + className
            }
            style={
                side === 'left'
                    ? { borderRight: '1px solid var(--color-divider)' }
                    : { borderLeft: '1px solid var(--color-divider)' }
            }
        >
            <Link href="/" style={wordmark}>
                SDPC
            </Link>

            {/*
             * The headline sits against the vertical middle, which is where
             * both final designs put it.
             *
             * A flex-1 wrapper and not justify-between: that spread three
             * children — wordmark, headline, proof row — and read as centred
             * only by accident. Removing the proof row left two, and the
             * headline dropped to the foot of the panel.
             */}
            <div className="flex flex-1 items-center">
                <div style={{ maxWidth: 460 }}>
                    <h1
                        style={{
                            margin: 0,
                            fontFamily: 'var(--font-heading)',
                            fontWeight: 'var(--font-heading-weight)',
                            fontSize: 'clamp(32px, 3.2vw, 44px)',
                            lineHeight: 1.14,
                            letterSpacing: '-0.025em',
                        }}
                    >
                        <Reveal phase={phase}>{headline}</Reveal>
                    </h1>

                    <p
                        style={{
                            margin: '18px 0 0',
                            maxWidth: 330,
                            fontSize: 13,
                            lineHeight: 1.65,
                            color: MUTED,
                        }}
                    >
                        {/* A beat behind the headline, so the two arrive as a
                        sequence rather than a block. */}
                        <Reveal phase={phase} delay={80}>
                            {body}
                        </Reveal>
                    </p>
                </div>
            </div>
        </aside>
    );
}
