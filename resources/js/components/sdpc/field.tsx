import { useId } from 'react';
import type { ReactNode } from 'react';

import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';

type FieldProps = {
    label: string;
    /** Rendered under the label; use for the "one per line" style guidance. */
    hint?: string;
    error?: string;
    required?: boolean;
    className?: string;
    /**
     * Receives the id to bind to the control so the label stays associated
     * without every caller having to invent one.
     */
    children: (props: { id: string; 'aria-invalid': boolean }) => ReactNode;
};

/**
 * The design's `.field` wrapper — a label stacked over a control, with
 * validation output in the slot the design reserves beneath it.
 *
 * `.field > label` is styled by the design system itself (12px, 5px margin,
 * 70% text), so the label here is a plain element rather than the shadcn one.
 */
export default function Field({
    label,
    hint,
    error,
    required = false,
    className,
    children,
}: FieldProps) {
    const id = useId();

    return (
        <div className={cn('field', className)}>
            <label htmlFor={id}>
                {label}
                {required && (
                    <span
                        style={{ color: 'var(--color-accent)' }}
                        aria-hidden="true"
                    >
                        {' '}
                        *
                    </span>
                )}
            </label>

            {hint && (
                <p
                    style={{
                        fontSize: '11px',
                        margin: '0 0 5px',
                        color: 'color-mix(in srgb, var(--color-text) 55%, transparent)',
                    }}
                >
                    {hint}
                </p>
            )}

            {children({ id, 'aria-invalid': Boolean(error) })}

            <InputError message={error} className="mt-1 text-[11px]" />
        </div>
    );
}
