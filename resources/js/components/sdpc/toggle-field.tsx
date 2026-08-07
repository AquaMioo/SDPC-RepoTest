import * as React from 'react';

import { cn } from '@/lib/utils';

type ToggleFieldProps = Omit<React.ComponentProps<'input'>, 'type'> & {
    label: string;
    description?: string;
};

/**
 * The SDPC design's `[data-switch]` row — a description on the left and a
 * pill toggle on the right, the whole row clickable.
 *
 * Built on a native checkbox rather than a Radix primitive: the design's
 * markup is already a checkbox plus a styled track, and this keeps it
 * submittable inside a plain form without extra dependencies.
 */
export default function ToggleField({
    label,
    description,
    className,
    ...props
}: ToggleFieldProps) {
    return (
        <label
            className={cn(
                'flex cursor-pointer items-center gap-3',
                props.disabled && 'cursor-not-allowed opacity-60',
                className,
            )}
        >
            <span className="mr-auto">
                <span className="block text-[13.5px]">{label}</span>
                {description && (
                    <span className="block text-[11.5px] text-muted-foreground">
                        {description}
                    </span>
                )}
            </span>

            <input type="checkbox" className="peer sr-only" {...props} />

            <span
                aria-hidden="true"
                className={cn(
                    'relative block h-5 w-9 shrink-0 rounded-full bg-ink-800 transition-colors',
                    'peer-checked:bg-primary/60',
                    'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring',
                    'after:absolute after:top-[3px] after:left-[3px] after:size-3.5 after:rounded-full after:bg-ink-400 after:transition-all',
                    'peer-checked:after:left-[19px] peer-checked:after:bg-brand-100',
                )}
            />
        </label>
    );
}
