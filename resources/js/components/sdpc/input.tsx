import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * The latest value each date-like input may take, unless the caller sets its
 * own `max`.
 *
 * A browser's date box lets the year run to six digits (up to 275760), so
 * "2026" typed with one key too many became 20266. A four-digit `max` stops
 * the year segment at four digits (QA 2026-09-19).
 */
const FOUR_DIGIT_YEAR_MAX: Partial<Record<string, string>> = {
    date: '9999-12-31',
    'datetime-local': '9999-12-31T23:59',
    month: '9999-12',
};

/**
 * The design's `.input`. Height, padding, caret colour and the accent focus
 * border all come from the design system, so this only forwards props.
 */
function Input({
    className,
    type,
    max,
    ...props
}: React.ComponentProps<'input'>) {
    return (
        <input
            data-slot="input"
            type={type}
            max={max ?? (type ? FOUR_DIGIT_YEAR_MAX[type] : undefined)}
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
