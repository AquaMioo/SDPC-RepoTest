import { Link, router, usePage } from '@inertiajs/react';
import { BellIcon, SignOutIcon } from '@phosphor-icons/react';
import type { CSSProperties, ReactNode } from 'react';

import { Btn } from '@/components/sdpc/btn';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useMod } from '@/hooks/use-mod';
import { logout } from '@/routes';
import { dashboard, issues, monitoring } from '@/routes/admin';
import { index as adminUsers } from '@/routes/admin/users';

type NavItem = {
    label: string;
    href?: string;
    pending?: string;
};

const MUTED = 'color-mix(in srgb, var(--color-text) 45%, transparent)';

const NAV_LINK: CSSProperties = {
    background: 'none',
    border: 0,
    padding: '4px 0',
    font: 'inherit',
    fontSize: 14,
    color: 'var(--color-text)',
    cursor: 'pointer',
    textDecoration: 'none',
};

/**
 * The admin portal shell.
 *
 * Deliberately does NOT set data-mod="user", so it falls through to the base
 * palette the design gives admin screens: #0c1614 ground, #1b2f28 surface,
 * #3f8f70 accent — a different, deeper green than the user app's #e3e3e3.
 */
export default function AdminLayout({ children }: { children: ReactNode }) {
    const page = usePage();

    useMod('admin');

    const navigation: NavItem[] = [
        { label: 'Overview', href: dashboard.url() },
        { label: 'Users', href: adminUsers.url() },
        /*
         * Credentials and Businesses are gone. Enrolment is checked by the
         * verification provider and a business is verified on registration, so
         * neither queue had anything left for an administrator to decide.
         *
         * Postings and Content are gone from the nav too, but not from the
         * app: both fold into the Overview screen, which is what the scope
         * calls the Dashboard Overview.
         */
        { label: 'Issues', href: issues.url() },
        { label: 'Monitoring', href: monitoring.url() },
    ];

    return (
        <div
            data-mod="admin"
            style={{
                minHeight: '100vh',
                background: 'var(--color-bg)',
                color: 'var(--color-text)',
                fontFamily: 'var(--font-body)',
            }}
        >
            <header
                style={{
                    position: 'sticky',
                    top: 0,
                    zIndex: 20,
                    background:
                        'color-mix(in srgb, var(--color-bg) 88%, transparent)',
                    backdropFilter: 'blur(10px)',
                }}
            >
                <div
                    style={{
                        maxWidth: 1180,
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
                            marginRight: 'auto',
                        }}
                    >
                        <Link
                            href={dashboard.url()}
                            style={{
                                fontFamily: 'var(--font-heading)',
                                fontWeight: 600,
                                fontSize: 18,
                                letterSpacing: '-0.02em',
                                color: 'var(--color-accent)',
                                textDecoration: 'none',
                            }}
                        >
                            SDPCC
                        </Link>
                        <span
                            style={{
                                fontSize: 10,
                                letterSpacing: '.14em',
                                textTransform: 'uppercase',
                                color: MUTED,
                            }}
                        >
                            Admin
                        </span>
                    </div>

                    <nav
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 22,
                        }}
                    >
                        {navigation.map((item) => (
                            <NavLink
                                key={item.label}
                                item={item}
                                currentPath={page.url}
                            />
                        ))}
                    </nav>

                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                            marginLeft: 26,
                            fontSize: 18,
                        }}
                    >
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span
                                    aria-label="Notifications"
                                    className="btn btn-icon"
                                    style={{ color: MUTED, cursor: 'not-allowed' }}
                                >
                                    <BellIcon />
                                </span>
                            </TooltipTrigger>
                            <TooltipContent>
                                Arrives with the Notifications module
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Btn
                                    icon
                                    variant="bare"
                                    aria-label="Log out"
                                    style={{ color: 'var(--color-text)' }}
                                    onClick={() => router.post(logout.url())}
                                >
                                    <SignOutIcon />
                                </Btn>
                            </TooltipTrigger>
                            <TooltipContent>Log out</TooltipContent>
                        </Tooltip>
                    </div>
                </div>

                <div
                    style={{
                        height: 1,
                        background:
                            'linear-gradient(to right,transparent,var(--color-divider) 48px,var(--color-divider) calc(100% - 48px),transparent)',
                    }}
                />
            </header>

            <main>{children}</main>
        </div>
    );
}

function NavLink({
    item,
    currentPath,
}: {
    item: NavItem;
    currentPath: string;
}) {
    if (!item.href) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span
                        style={{ ...NAV_LINK, color: MUTED, cursor: 'not-allowed' }}
                    >
                        {item.label}
                    </span>
                </TooltipTrigger>
                <TooltipContent>{item.pending}</TooltipContent>
            </Tooltip>
        );
    }

    const isCurrent = currentPath.split('?')[0] === item.href.split('?')[0];

    return (
        <Link
            href={item.href}
            data-nav=""
            aria-current={isCurrent ? 'page' : undefined}
            style={NAV_LINK}
        >
            {item.label}
        </Link>
    );
}
