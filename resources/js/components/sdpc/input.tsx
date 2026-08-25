import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * The design's `.input`. Height, padding, caret colour and the accent focus
 * border all come from the design system, so this only forwards props.
 */
function Input({ className, ...props }: React.ComponentProps<'input'>) {
    return (
        <input
            data-slot="input"
            className={cn('input', className)}
            {...props}
        />
    );
}

/**
 * The design's `textarea.input` — same token set, 90px minimum height.
 */
function Textarea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn('input', className)}
            {...props}
        />
    );
}

/**
 * A `<select>` wearing the `.input` styling, for the design's dropdowns.
 */
function Select({ className, ...props }: React.ComponentProps<'select'>) {
    return (
        <select
            data-slot="select"
            className={cn('input', className)}
            {...props}
        />
    );
}

export { Input, Select, Textarea };
