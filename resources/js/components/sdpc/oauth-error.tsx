import { usePage } from '@inertiajs/react';

/**
 * The reason the last Google or Microsoft attempt was turned away.
 *
 * Both flows leave through a full page redirect and come back as a fresh GET,
 * so their failures never pass through a form submission and the `<Form>`
 * render prop never sees them. Inertia shares the session error bag on every
 * response though, which is what this reads.
 *
 * Keyed on the provider rather than `email` so it can never be confused with a
 * validation error on the form's own email field.
 */
export default function OAuthError({
    provider,
}: {
    provider: 'google' | 'microsoft';
}) {
    const message = usePage<{
        errors: Partial<Record<'google' | 'microsoft', string>>;
    }>().props.errors?.[provider];

    if (!message) {
        return null;
    }

    return (
        <div
            role="alert"
            data-test={`${provider}-auth-error`}
            className="border border-red-600/40 bg-red-600/10 px-3 py-2 text-center text-xs leading-relaxed text-red-600 dark:border-red-400/40 dark:bg-red-400/10 dark:text-red-400"
            style={{ borderRadius: 'var(--radius-md)' }}
        >
            {message}
        </div>
    );
}
