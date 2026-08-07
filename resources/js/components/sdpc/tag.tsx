import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * The design's `.tag` primitive — used for skills, statuses and filter chips.
 *
 * Emits the Nocturne classes rather than Tailwind equivalents so the tag keeps
 * its exact 3px/10px padding and 6px radius (`calc(--radius-md * 0.75)`).
 */
const tagVariants = cva('tag', {
    variants: {
        variant: {
            accent: 'tag-accent',
            'accent-2': 'tag-accent-2',
            neutral: 'tag-neutral',
            outline: 'tag-outline',
        },
    },
    defaultVariants: {
        variant: 'neutral',
    },
});

function Tag({
    className,
    variant,
    ...props
}: React.ComponentProps<'span'> & VariantProps<typeof tagVariants>) {
    return (
        <span
            data-slot="tag"
            className={cn(tagVariants({ variant }), className)}
            {...props}
        />
    );
}

export { Tag, tagVariants };
