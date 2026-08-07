import { GoogleLogoIcon } from '@phosphor-icons/react';

import { Btn } from '@/components/sdpc/btn';

/**
 * Starts the Google OAuth redirect.
 *
 * A full page navigation on purpose: Inertia cannot follow a cross-origin
 * redirect, so this is a plain anchor rather than an Inertia <Link>.
 *
 * The design specifies a secondary block button with an accent-coloured
 * Phosphor glyph — not Google's multicolour mark — so that is what ships.
 */
export default function GoogleAuthButton({
    href,
    label = 'Continue with Google',
    tabIndex,
}: {
    /** The URL that starts the Google OAuth flow for this portal. */
    href: string;
    label?: string;
    tabIndex?: number;
}) {
    return (
        <Btn asChild variant="secondary" block style={{ gap: 9 }}>
            <a href={href} tabIndex={tabIndex} data-test="google-auth-button">
                <GoogleLogoIcon style={{ color: 'var(--color-accent)' }} />
                {label}
            </a>
        </Btn>
    );
}
