import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { useMod } from '@/hooks/use-mod';

/**
 * The design's auth shell: a full-height centred column with the wordmark above
 * the card, lit by an accent glow bleeding down from off-screen.
 *
 * The heading lives inside each page's own `.card` in this design, so `title`
 * and `description` are accepted (pages still set them via `Page.layout`) but
 * are not rendered here.
 */
export default function AuthLayout({
    children,
}: {
    title?: string;
    description?: string;
    children: ReactNode;
}) {
    useMod('user');

    return (
        <div
            data-mod="user"
            style={{
                minHeight: '100vh',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                gap: 26,
                padding: '48px 24px',
                position: 'relative',
                overflow: 'hidden',
                background: 'var(--color-bg)',
                color: 'var(--color-text)',
                fontFamily: 'var(--font-body)',
            }}
        >
            <div
                style={{
                    position: 'absolute',
                    top: -180,
                    left: '50%',
                    width: 760,
                    height: 420,
                    transform: 'translateX(-50%)',
                    background:
                        'radial-gradient(50% 50% at 50% 50%, color-mix(in srgb, var(--color-accent) 20%, transparent), transparent 70%)',
                    filter: 'blur(30px)',
                    pointerEvents: 'none',
                }}
            />

            <Link
                href="/"
                style={{
                    fontFamily: 'var(--font-heading)',
                    fontWeight: 600,
                    fontSize: 24,
                    letterSpacing: '-0.02em',
                    color: 'var(--color-accent)',
                    position: 'relative',
                    textDecoration: 'none',
                }}
            >
                SDPCC
            </Link>

            {children}
        </div>
    );
}
