import { Link, usePage } from '@inertiajs/react';
import {
    BellIcon,
    ChatCircleIcon,
    GearSixIcon,
    UserCircleIcon,
} from '@phosphor-icons/react';
import type { CSSProperties, ReactNode } from 'react';

import AccountStatusBanner from '@/components/account-status-banner';
import IncomingCallAlert from '@/components/messaging/incoming-call-alert';
import AccountSessionGuard from '@/components/sdpc/account-session-guard';
import { Btn } from '@/components/sdpc/btn';
import { NotificationMenu } from '@/components/sdpc/notification-menu';
import type { NotificationRow } from '@/components/sdpc/notification-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { useMod } from '@/hooks/use-mod';
import { dashboard, projectManagement } from '@/routes';
import { index as agreementsIndex } from '@/routes/agreements';
import { dashboard as clientDashboard } from '@/routes/client';
import { edit as clientProfileEdit } from '@/routes/client-profile';
import { index as messagesIndex } from '@/routes/messages';
import { edit as profileEdit } from '@/routes/profile';
import { index as recruitIndex } from '@/routes/recruit';
import { index as studentBoard } from '@/routes/student/board';
import { edit as studentProfileEdit } from '@/routes/student/profile';
import { index as teamsIndex } from '@/routes/teams';
import { index as transactionsIndex } from '@/routes/transactions';

type SharedProps = {
    auth?: {
        user?: { name: string; role: string } | null;
        role?: string | null;
        /** UserStatus value; "deactivated" confines the account to settings. */
        status?: string | null;
    };
    unreadMessages?: number;
    unreadNotifications?: number;
    /** The bell's menu, shared by HandleInertiaRequests on every screen. */
    recentNotifications?: NotificationRow[];
    /** False on a normal boot — the ledger is built but switched off. */
    billingEnabled?: boolean;
};

type NavItem = {
    label: string;
    href?: string;
    /** Set when the destination belongs to a module that is not built yet. */
    pending?: string;
};

/*
 * Header icon size, passed to each icon explicitly.
 *
 * It cannot be set by font-size on the row: Phosphor icons draw at 1em, and
 * .btn in nocturne.css sets font-size: 14px on the button wrapping each one, so
 * anything inherited from above is overridden and the icons stayed at 14px.
 * .btn-icon is a 36px box, which is what made them look mis-sized inside it.
 */
const NAV_ICON = 22;

const BRAND: CSSProperties = {
    fontFamily: 'var(--font-heading)',
    fontWeight: 600,
    fontSize: 20,
    letterSpacing: '-0.02em',
    color: 'var(--color-accent)',
    textDecoration: 'none',
};

const ROLE_LABEL: CSSProperties = {
    fontSize: 20,
    letterSpacing: '.14em',
    textTransform: 'uppercase',
    color: 'color-mix(in srgb, var(--color-text) 45%, transparent)',
};

/*
 * The dash between the wordmark and the label. It carries no letter-spacing of
 * its own: .14em is trailing space after a character, which on a single dash
 * lands entirely on its right and reads as an off-centre separator.
 */
const ROLE_SEPARATOR: CSSProperties = {
    fontSize: ROLE_LABEL.fontSize,
    color: ROLE_LABEL.color,
};

/*
 * Colour is deliberately absent: it lives on a[data-nav] in nocturne.css,
 * with the hover and current-page states an inline value made unreachable.
 */
const NAV_LINK: CSSProperties = {
    background: 'none',
    border: 0,
    padding: '4px 0',
    font: 'inherit',
    fontSize: 14,
    cursor: 'pointer',
    textDecoration: 'none',
};

const MUTED = 'color-mix(in srgb, var(--color-text) 45%, transparent)';

/** Why every destination is greyed out for a deactivated account. */
const DEACTIVATED =
    'Unavailable while your account is deactivated. Settings is still open.';

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
    const billingEnabled = page.props.billingEnabled ?? false;

    /*
     * A deactivated account signs in only to reach Settings and its appeal
     * (ConfineDeactivatedAccounts on the server). Every destination here is
     * still drawn, greyed out with the reason, so the account can see what it
     * has lost rather than wonder where the navigation went.
     */
    const isDeactivated = page.props.auth?.status === 'deactivated';

    useMod('user');

    /** Each role's own front door — the client module 403s a student. */
    const home = isDeactivated
        ? profileEdit.url()
        : isStudent
          ? dashboard.url(team.slug)
          : clientDashboard.url(team.slug);

    /*
     * The student links point at routes/student.php, never at the client
     * module's — everything under routes/client.php sits behind
     * EnsureUserIsClient, so sending a student to Recruit or Projects would
     * abort with a 403.
     */
    /*
     * The ledger ships dormant, so while config('billing.enabled') is off its
     * nav item is absent rather than present-and-greyed. The routes and the
     * screen are still there and still 404 behind EnsureBillingIsEnabled —
     * switching the flag on is what puts the link back, and nothing here was
     * deleted to make that harder.
     */
    const billing: NavItem | null = billingEnabled
        ? { label: 'Transaction', href: transactionsIndex.url(team.slug) }
        : null;

    const navigation: NavItem[] = (
        isStudent
            ? [
                  { label: 'Dashboard', href: dashboard.url(team.slug) },
                  { label: 'Get Client', href: studentBoard.url(team.slug) },
                  /*
                   * Was "Workflow". Both sides now land on the same
                   * Project Management screen for a signed build; the
                   * applications list lives on as a section of it.
                   */
                  {
                      label: 'Project Management',
                      href: projectManagement.url(team.slug),
                  },
                  billing === null
                      ? null
                      : { ...billing, label: 'Performance' },
                  { label: 'Agreement', href: agreementsIndex.url(team.slug) },
                  /*
                   * Teams is a destination of its own now rather than a row
                   * under Settings. Last on both navs, after Agreement, so the
                   * two sides read the same way round.
                   *
                   * teamsIndex takes no team argument: settings/teams is one of
                   * the few screens not mounted on the {current_team} prefix,
                   * because it is where you go to change which team that is.
                   */
                  { label: 'Team', href: teamsIndex.url() },
              ]
            : [
                  { label: 'Dashboard', href: clientDashboard.url(team.slug) },
                  { label: 'Recruit', href: recruitIndex.url(team.slug) },
                  billing,
                  /*
                   * Was "Project Process", the postings list. The postings
                   * are still one click away from the new screen's "Your
                   * postings" button and from the dashboard.
                   */
                  {
                      label: 'Project Management',
                      href: projectManagement.url(team.slug),
                  },
                  { label: 'Agreement', href: agreementsIndex.url(team.slug) },
                  { label: 'Team', href: teamsIndex.url() },
              ]
    )
        .filter((item): item is NavItem => item !== null)
        .map((item) =>
            isDeactivated ? { label: item.label, pending: DEACTIVATED } : item,
        );

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
                    className="page-shell app-bar"
                    style={{
                        maxWidth: 'clamp(1320px, 100vw - 320px, 1600px)',
                        paddingBlock: 14,
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'baseline',
                            gap: 8,
                            flex: 'none',
                        }}
                    >
                        <Link href={home} style={BRAND}>
                            SDPC
                        </Link>

                        {/*
                         * Which side of the platform you are on. This layout
                         * serves students and clients both — the nav is picked
                         * from auth.role — so without a label the only clue is
                         * which links happen to be present, which is no clue at
                         * all to somebody seeing the screen for the first time.
                         * Same treatment as the wordmark on the admin portal.
                         */}
                        <span aria-hidden="true" style={ROLE_SEPARATOR}>
                            -
                        </span>

                        <span style={ROLE_LABEL}>
                            {isStudent ? 'Student' : 'Client'}
                        </span>
                    </div>

                    <nav className="app-bar-nav">
                        {navigation.map((item) => (
                            <NavLink
                                key={item.label}
                                item={item}
                                currentPath={page.url}
                            />
                        ))}
                    </nav>

                    <div className="app-bar-actions" style={{ fontSize: 18 }}>
                        <IconAction
                            label="Messages"
                            href={
                                isDeactivated
                                    ? undefined
                                    : messagesIndex.url(team.slug)
                            }
                            pending={DEACTIVATED}
                            badge={
                                isDeactivated
                                    ? 0
                                    : (page.props.unreadMessages ?? 0)
                            }
                        >
                            <ChatCircleIcon size={NAV_ICON} />
                        </IconAction>
                        {/*
                         * The bell opens its own menu rather than navigating.
                         * Everything else on this row is still a plain link.
                         */}
                        {isDeactivated ? (
                            <IconAction
                                label="Notifications"
                                pending={DEACTIVATED}
                            >
                                <BellIcon size={NAV_ICON} />
                            </IconAction>
                        ) : (
                            <NotificationMenu
                                rows={page.props.recentNotifications ?? []}
                                unread={page.props.unreadNotifications ?? 0}
                                teamSlug={team.slug}
                            />
                        )}
                        {isStudent ? (
                            <IconAction
                                label="Your profile"
                                href={
                                    isDeactivated
                                        ? undefined
                                        : studentProfileEdit.url(team.slug)
                                }
                                pending={DEACTIVATED}
                            >
                                <UserCircleIcon size={NAV_ICON} />
                            </IconAction>
                        ) : (
                            <IconAction
                                label="Business profile"
                                href={
                                    isDeactivated
                                        ? undefined
                                        : clientProfileEdit.url(team.slug)
                                }
                                pending={DEACTIVATED}
                            >
                                <UserCircleIcon size={NAV_ICON} />
                            </IconAction>
                        )}
                        <IconAction label="Settings" href={profileEdit.url()}>
                            <GearSixIcon size={NAV_ICON} />
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

            <AccountStatusBanner />
            <AccountSessionGuard />
            {/* Calls happen in messages, which a deactivated account cannot open. */}
            {!isDeactivated && <IncomingCallAlert />}

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
    badge = 0,
    children,
}: {
    label: string;
    href?: string;
    pending?: string;
    /** Unread count; anything above zero paints a dot on the icon. */
    badge?: number;
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
                <Btn
                    asChild
                    icon
                    variant="bare"
                    style={{ color: 'var(--color-text)', position: 'relative' }}
                >
                    <Link
                        href={href}
                        aria-label={
                            badge > 0 ? `${label} (${badge} unread)` : label
                        }
                    >
                        {children}
                        {badge > 0 && (
                            <span
                                style={{
                                    position: 'absolute',
                                    top: 2,
                                    right: 2,
                                    minWidth: 8,
                                    height: 8,
                                    borderRadius: 4,
                                    background: 'var(--color-accent)',
                                }}
                            />
                        )}
                    </Link>
                </Btn>
            </TooltipTrigger>
            <TooltipContent>
                {badge > 0 ? `${label} · ${badge} unread` : label}
            </TooltipContent>
        </Tooltip>
    );
}
