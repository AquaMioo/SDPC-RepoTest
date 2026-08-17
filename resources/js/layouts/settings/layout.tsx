import { Link } from '@inertiajs/react';
import {
    LockSimpleIcon,
    SignOutIcon,
    UserIcon,
    UsersThreeIcon,
} from '@phosphor-icons/react';
import type { ComponentType, PropsWithChildren, ReactNode } from 'react';

import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as teams } from '@/routes/teams';
import type { NavItem } from '@/types';

const SHELL: React.CSSProperties = {
    maxWidth: 1120,
    margin: '0 auto',
    padding: '28px 32px 72px',
    display: 'grid',
    gridTemplateColumns: '216px minmax(0,1fr)',
    gap: 24,
    alignItems: 'start',
};

const KICKER: React.CSSProperties = {
    fontSize: 11,
    letterSpacing: '.08em',
    textTransform: 'uppercase',
    color: 'color-mix(in srgb, var(--color-text) 45%, transparent)',
    padding: '4px 10px 8px',
};

/** The design's sidebar row — a flush button, not a shadcn one. */
const ROW: React.CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    gap: 10,
    width: '100%',
    padding: '8px 10px',
    border: 0,
    borderRadius: 'var(--radius-md)',
    background: 'none',
    font: 'inherit',
    fontSize: 13.5,
    color: 'var(--color-text)',
    cursor: 'pointer',
    textAlign: 'left',
    textDecoration: 'none',
};

const RULE: React.CSSProperties = {
    height: 1,
    background: 'var(--color-divider)',
    margin: '6px 10px',
};

type SettingsNavItem = NavItem & { icon: ComponentType<{ size?: number }> };

/*
 * Only rows that lead somewhere real. The design also sketches Notifications,
 * Privacy, Portfolio, Activity and Help, but those have no route yet and a
 * nav row that goes nowhere is worse than one that is absent.
 */
const navItems: SettingsNavItem[] = [
    { title: 'Account', href: editProfile(), icon: UserIcon },
    { title: 'Security', href: editSecurity(), icon: LockSimpleIcon },
];

const teamItems: SettingsNavItem[] = [
    { title: 'Teams', href: teams(), icon: UsersThreeIcon },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const row = (item: SettingsNavItem): ReactNode => {
        const Icon = item.icon;

        return (
            <Link
                key={toUrl(item.href)}
                href={item.href}
                data-tab=""
                aria-current={
                    isCurrentOrParentUrl(item.href) ? 'page' : undefined
                }
                style={ROW}
            >
                <Icon size={16} />
                {item.title}
            </Link>
        );
    };

    return (
        <div style={SHELL} data-screen-label="Settings">
            <div
                className="card elev-sm"
                style={{
                    padding: '14px 10px',
                    gap: 2,
                    position: 'sticky',
                    top: 88,
                }}
            >
                <div style={KICKER}>Settings</div>

                {navItems.map(row)}

                <div style={RULE} />

                {teamItems.map(row)}

                <div style={RULE} />

                <Link href={logout()} as="button" data-tab="" style={ROW}>
                    <SignOutIcon size={16} />
                    Log out
                </Link>
            </div>

            <div>{children}</div>
        </div>
    );
}
