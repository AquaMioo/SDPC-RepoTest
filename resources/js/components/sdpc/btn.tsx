import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * The design's `.btn` primitive.
 *
 * Nocturne buttons are outline-first: `btn-primary` is an accent border with a
 * transparent fill, not a solid block. Emitting the design's own classes keeps
 * that, plus the hover/active `color-mix` tints, exactly as specified.
 *
 * Pass `asChild` to render an Inertia `<Link>` (or anchor) with button styling.
 */
const btnVariants = cva('btn', {
    variants: {
        variant: {
            primary: 'btn-primary',
            secondary: 'btn-secondary',
            ghost: 'btn-ghost',
            bare: '',
        },
        icon: {
            true: 'btn-icon',
            false: '',
        },
        block: {
            true: 'btn-block',
            false: '',
        },
    },
    defaultVariants: {
        variant: 'secondary',
        icon: false,
        block: false,
    },
});

type BtnProps = React.ComponentProps<'button'> &
    VariantProps<typeof btnVariants> & {
        asChild?: boolean;
    };

function Btn({
    className,
    variant,
    icon,
    block,
    asChild = false,
    type,
    ...props
}: BtnProps) {
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            data-slot="btn"
            className={cn(btnVariants({ variant, icon, block }), className)}
            // Buttons inside Inertia forms default to submit; anything rendered
            // through asChild is a link and must not carry a type at all.
            {...(asChild ? {} : { type: type ?? 'button' })}
            {...props}
        />
    );
}

export { Btn, btnVariants };
