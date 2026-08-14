import type { ReactNode } from 'react';

import { useMod } from '@/hooks/use-mod';

/**
 * Shell for the screens a signed-out visitor can reach: the landing page and
 * the login / sign up cards. Uses the design's `data-mod="user"` palette
 * (#e3e3e3 ground, olive accent) — the same one the signed-in app wears.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    useMod('user');

    return (
        <div
            data-mod="user"
            style={{
                minHeight: '100vh',
                background: 'var(--color-bg)',
                fontFamily: 'var(--font-body)',
                color: 'var(--color-text)',
            }}
        >
            {children}
        </div>
    );
}
