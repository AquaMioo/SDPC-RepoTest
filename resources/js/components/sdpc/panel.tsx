import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * The design's `.card` primitive.
 *
 * This emits the Nocturne class names verbatim rather than re-expressing them
 * as Tailwind utilities: the design's padding (8.4px), gap (5.6px) and radius
 * (8px) come off a 2.8px scale that Tailwind's 4px scale cannot represent.
 */
const panelVariants = cva('card', {
    variants: {
        elevation: {
            sm: 'elev-sm',
            md: 'elev-md',
            lg: 'elev-lg',
            none: '',
        },
    },
    defaultVariants: {
        elevation: 'sm',
    },
});

const PADDING = {
    none: '0',
    sm: 'var(--space-2)',
    md: 'var(--space-3)',
    lg: 'var(--space-4)',
} as const;

const GAP = {
    none: '0',
    sm: 'var(--space-1)',
    md: 'var(--space-2)',
    lg: 'var(--space-3)',
} as const;

type PanelProps = React.ComponentProps<'div'> &
    VariantProps<typeof panelVariants> & {
        /** Overrides the design's default `--space-3` padding. */
        padding?: keyof typeof PADDING;
        /** Overrides the design's default `--space-2` gap. */
        gap?: keyof typeof GAP;
    };

function Panel({
    className,
    elevation,
    padding,
    gap,
    style,
    ...props
}: PanelProps) {
    return (
        <div
            data-slot="panel"
            className={cn(panelVariants({ elevation }), className)}
            style={{
                ...(padding ? { padding: PADDING[padding] } : {}),
                ...(gap ? { gap: GAP[gap] } : {}),
                ...style,
            }}
            {...props}
        />
    );
}

/**
 * The design's `.card-kicker` — an uppercase micro-label above panel content.
 */
function PanelKicker({ className, ...props }: React.ComponentProps<'span'>) {
    return (
        <span
            data-slot="panel-kicker"
            className={cn('card-kicker', className)}
            {...props}
        />
    );
}

/**
 * The design's `.card-title`.
 */
function PanelTitle({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="panel-title"
            className={cn('card-title', className)}
            {...props}
        />
    );
}

/**
 * The design's `.card-meta` — the muted footer row of a card.
 */
function PanelMeta({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="panel-meta"
            className={cn('card-meta', className)}
            {...props}
        />
    );
}

/**
 * Hairline separator. Uses the design's `.hr`, which fades to transparent 48px
 * from each end — a Nocturne signature, not a plain 1px line.
 */
function PanelDivider({ className, ...props }: React.ComponentProps<'hr'>) {
    return (
        <hr
            data-slot="panel-divider"
            className={cn('hr', className)}
            {...props}
        />
    );
}

/**
 * The accent-washed panel the design uses for the AI matching and live preview
 * sidebars.
 */
function PanelAccent({ style, ...props }: PanelProps) {
    return (
        <Panel
            elevation="md"
            style={{
                background:
                    'linear-gradient(160deg, color-mix(in srgb, var(--color-accent) 14%, var(--color-surface)), var(--color-surface) 60%)',
                ...style,
            }}
            {...props}
        />
    );
}

export {
    Panel,
    PanelAccent,
    PanelDivider,
    PanelKicker,
    PanelMeta,
    PanelTitle,
    panelVariants,
};
