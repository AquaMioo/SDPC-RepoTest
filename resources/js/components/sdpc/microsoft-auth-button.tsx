import { Btn } from '@/components/sdpc/btn';

/**
 * Microsoft's four-square logo, in its own colours.
 *
 * Inline for the same reason as the Google mark: Microsoft's sign-in branding
 * guidelines do not allow the logo to be recoloured to match an icon set.
 */
function MicrosoftMark() {
    return (
        <svg
            width="17"
            height="17"
            viewBox="0 0 21 21"
            aria-hidden="true"
            focusable="false"
        >
            <rect x="1" y="1" width="9" height="9" fill="#F25022" />
            <rect x="11" y="1" width="9" height="9" fill="#7FBA00" />
            <rect x="1" y="11" width="9" height="9" fill="#00A4EF" />
            <rect x="11" y="11" width="9" height="9" fill="#FFB900" />
        </svg>
    );
}

/**
 * Starts the school Microsoft sign-in.
 *
 * A full page navigation on purpose: Inertia cannot follow a cross-origin
 * redirect, so this is a plain anchor rather than an Inertia <Link>. Styled as
 * the Google button's twin, so the two read as the same kind of action.
 */
export default function MicrosoftAuthButton({
    href,
    label = 'Continue with Microsoft',
    tabIndex,
}: {
    /** The URL that starts the Microsoft OAuth flow. */
    href: string;
    label?: string;
    tabIndex?: number;
}) {
    return (
        <Btn asChild variant="secondary" block style={{ gap: 9 }}>
            <a
                href={href}
                tabIndex={tabIndex}
                data-test="microsoft-auth-button"
            >
                <MicrosoftMark />
                {label}
            </a>
        </Btn>
    );
}
