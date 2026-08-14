import { Link, usePage } from '@inertiajs/react';
import {
    BellIcon,
    ChatCircleIcon,
    GearSixIcon,
    UserCircleIcon,
} from '@phosphor-icons/react';
import type { CSSProperties, ReactNode } from 'react';

import { Btn } from '@/components/sdpc/btn';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { useMod } from '@/hooks/use-mod';
import { dashboard as clientDashboard } from '@/routes/client';
import { edit as clientProfileEdit } from '@/routes/client-profile';
import { edit as profileEdit } from '@/routes/profile';
import { index as projectsIndex } from '@/routes/projects';
import { index as recruitIndex } from '@/routes/recruit';

type SharedProps = {
    auth?: { user?: { name: string; role: string } | null; role?: string | null };
};

type NavItem = {
    label: string;
    href?: string;
    /** Set when the destination belongs to a module that is not built yet. */
    pending?: string;
};

const BRAND: CSSProperties = {
    fontFamily: 'var(--font-heading)',
    fontWeight: 600,
    fontSize: 18,
    letterSpacing: '-0.02em',
    color: 'var(--color-accent)',
    textDecoration: 'none',
};

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

const MUTED = 'color-mix(in srgb, var(--color-text) 45%, transparent)';

/**
 * The signed-in shell for clients and students.
 *
 * Wears the design's `data-mod="user"` palette (#e3e3e3 ground, olive accent).
 * The design's role toggle in this header is a prototype control for previewing
 * both navs; a real user's role is fixed, so the nav is chosen from auth.role
 * instead of being switchable.
 */
export default function ClientLayout({ children }: { children: ReactNode }) {
    const page = usePage<SharedProps>();
    const team = useCurrentTeam();
    const user = page.props.auth?.user ?? null;
    const isStudent = (page.props.auth?.role ?? user?.role) === 'student';

    useMod('user');

    const navigation: NavItem[] = isStudent
        ? [
              { label: 'Dashboard', href: clientDashboard.url(team.slug) },
              { label: 'Get Client', href: recruitIndex.url(team.slug) },
              { label: 'Workflow', href: projectsIndex.url(team.slug) },
              {
                  label: 'Performance',
                  pending: 'Arrives with the Payments module',
              },
              {
                  label: 'Agreement',
                  pending: 'Arrives with the Contracts module',
              },
          ]
        : [
              { label: 'Dashboard', href: clientDashboard.url(team.slug) },
              { label: 'Recruit', href: recruitIndex.url(team.slug) },
              {
                  label: 'Transaction',
                  pending: 'Arrives with the Payments module',
              },
              { label: 'Project Process', href: projectsIndex.url(team.slug) },
              {
                  label: 'Agreement',
                  pending: 'Arrives with the Contracts module',
              },
          ];

    return (
        <div
            data-mod="user"
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
                        maxWidth: 1320,
                        margin: '0 auto',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 26,
                        padding: '14px 32px',
                    }}
                >
                    <Link href={clientDashboard.url(team.slug)} style={BRAND}>
                        SDPCC
                    </Link>

                    <nav
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 22,
                            margin: '0 auto',
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

                    {user && (
                        <span
                            className="tag tag-outline"
                            style={{ textTransform: 'capitalize' }}
                            title="Your account type"
                        >
                            {page.props.auth?.role ?? user.role}
                        </span>
                    )}

                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                            fontSize: 18,
                        }}
                    >
                        <IconAction
                            label="Messages"
                            pending="Arrives with the Messaging module"
                        >
                            <ChatCircleIcon />
                        </IconAction>
                        <IconAction
                            label="Notifications"
                            pending="Arrives with the Notifications module"
                        >
                            <BellIcon />
                        </IconAction>
                        <IconAction
                            label="Business profile"
                            href={clientProfileEdit.url(team.slug)}
                        >
                            <UserCircleIcon />
                        </IconAction>
                        <IconAction label="Settings" href={profileEdit.url()}>
                            <GearSixIcon />
                        </IconAction>
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
                        style={{
                            ...NAV_LINK,
                            color: MUTED,
                            cursor: 'not-allowed',
                        }}
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

function IconAction({
    label,
    href,
    pending,
    children,
}: {
    label: string;
    href?: string;
    pending?: string;
    children: ReactNode;
}) {
    if (!href) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span
                        aria-label={label}
                        className="btn btn-icon"
                        style={{ color: MUTED, cursor: 'not-allowed' }}
                    >
                        {children}
                    </span>
                </TooltipTrigger>
                <TooltipContent>{pending}</TooltipContent>
            </Tooltip>
        );
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Btn asChild icon variant="bare" style={{ color: 'var(--color-text)' }}>
                    <Link href={href} aria-label={label}>
                        {children}
                    </Link>
                </Btn>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
