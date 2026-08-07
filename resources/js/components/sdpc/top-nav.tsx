import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * The sticky translucent header the design puts above every signed-in screen.
 *
 * Identical structure for the user and admin shells — only the max width, the
 * brand suffix and the links differ — so both share it rather than duplicating
 * the blur, the padding and the fading rule underneath.
 */

export type NavItem = {
    label: string;
    href: string;
    /** Matched against the current path to set aria-current="page". */
    match?: string;
};

const BRAND: React.CSSProperties = {
    fontFamily: 'var(--font-heading)',
    fontWeight: 600,
    fontSize: 18,
    letterSpacing: '-0.02em',
    color: 'var(--color-accent)',
    cursor: 'pointer',
    textDecoration: 'none',
};

const LINK: React.CSSProperties = {
    background: 'none',
    border: 0,
    padding: '4px 0',
    font: 'inherit',
    fontSize: 14,
    color: 'var(--color-text)',
    cursor: 'pointer',
    textDecoration: 'none',
};

export default function TopNav({
    items,
    actions,
    maxWidth = 1320,
    suffix,
}: {
    items: NavItem[];
    actions?: ReactNode;
    maxWidth?: number;
    /** e.g. "Admin", rendered next to the wordmark. */
    suffix?: string;
}) {
    const { url } = usePage();

    const isCurrent = (item: NavItem) => {
        const needle = item.match ?? item.href;

        return url === needle || url.startsWith(`${needle}/`) || url.startsWith(`${needle}?`);
    };

    return (
        <div
            style={{
                position: 'sticky',
                top: 0,
                zIndex: 20,
                background: 'color-mix(in srgb, var(--color-bg) 88%, transparent)',
                backdropFilter: 'blur(10px)',
            }}
        >
            <div
                style={{
                    maxWidth,
                    margin: '0 auto',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 26,
                    padding: '14px 32px',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'baseline',
                        gap: 8,
                        ...(suffix ? { marginRight: 'auto' } : {}),
                    }}
                >
                    <Link href="/" style={BRAND}>
                        SDPCC
                    </Link>
                    {suffix && (
                        <span
                            style={{
                                fontSize: 10,
                                letterSpacing: '.14em',
                                textTransform: 'uppercase',
                                color: 'color-mix(in srgb, var(--color-text) 45%, transparent)',
                            }}
                        >
                            {suffix}
                        </span>
                    )}
                </div>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 22,
                        ...(suffix ? {} : { margin: '0 auto' }),
                    }}
                >
                    {items.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            data-nav=""
                            aria-current={isCurrent(item) ? 'page' : undefined}
                            style={LINK}
                        >
                            {item.label}
                        </Link>
                    ))}
                </div>

                {actions && (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                            fontSize: 18,
                            ...(suffix ? { marginLeft: 26 } : {}),
                        }}
                    >
                        {actions}
                    </div>
                )}
            </div>

            {/* The design's fading rule, not a plain border. */}
            <div
                style={{
                    height: 1,
                    background:
                        'linear-gradient(to right,transparent,var(--color-divider) 48px,var(--color-divider) calc(100% - 48px),transparent)',
                }}
            />
        </div>
    );
}
