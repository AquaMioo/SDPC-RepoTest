import { usePage } from '@inertiajs/react';

/**
 * The reason the last Google attempt was turned away.
 *
 * The Google flow leaves through a full page redirect and comes back as a fresh
 * GET, so its failure never passes through a form submission and the `<Form>`
 * render prop never sees it. Inertia shares the session error bag on every
 * response though, which is what this reads.
 *
 * Keyed on `google` rather than `email` so it can never be confused with a
 * validation error on the login form's own email field.
 */
export default function GoogleAuthError() {
    const message = usePage<{ errors: { google?: string } }>().props.errors
        ?.google;

    if (!message) {
        return null;
    }

    return (
        <div
            role="alert"
            data-test="google-auth-error"
            className="border border-red-600/40 bg-red-600/10 px-3 py-2 text-center text-xs leading-relaxed text-red-600 dark:border-red-400/40 dark:bg-red-400/10 dark:text-red-400"
            style={{ borderRadius: 'var(--radius-md)' }}
        >
            {message}
        </div>
    );
}
