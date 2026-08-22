import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarBlankIcon,
    CircleIcon,
    MegaphoneIcon,
    PlusIcon,
    UserIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';

import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Btn } from '@/components/sdpc/btn';
import { Panel } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { process as studentProcess } from '@/routes/student';
import { index as studentBoard } from '@/routes/student/board';
import type { DashboardInvitation } from '@/types';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const DAY_INITIALS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

type CalendarDay = {
    day: number;
    date: string;
    isToday: boolean;
    isOutsideMonth: boolean;
    /** What the agreement has scheduled for that day, if anything. */
    milestone: string | null;
};

type Props = {
    project: {
        title: string;
        slug: string;
        client: string;
        dueDate: string | null;
        statusLabel: string;
        progress: number | null;
        team: { name: string; role: string | null; isAvailable: boolean }[];
    } | null;
    calendar: { label: string; days: CalendarDay[] };
    announcement: { body: string; updatedAt: string | null } | null;
    pendingInvitations?: DashboardInvitation[];
};

/**
 * The student's home screen.
 *
 * Mirrors the design's student dashboard: calendar, progress ring, project
 * team, then the announcements row. Every panel reads from the database and
 * falls back to an empty state — a student who has not been hired yet sees
 * what is missing rather than someone else's numbers.
 */
export default function StudentDashboard({
    project,
    calendar,
    announcement,
    pendingInvitations = [],
}: Props) {
    const page = usePage<{ auth?: { user?: { name: string } | null } }>();
    const currentTeam = useCurrentTeam();
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    return (
        <>
            <Head title="Dashboard" />

            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <div
                style={{
                    maxWidth: 1320,
                    margin: '0 auto',
                    padding: '30px 32px 72px',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        gap: 16,
                        marginBottom: 20,
                    }}
                >
                    <div style={{ marginRight: 'auto' }}>
                        <h3 style={{ margin: 0 }}>Welcome,</h3>
                        <div style={{ fontSize: 15, color: MUTED(60) }}>
                            {page.props.auth?.user?.name}
                        </div>
                    </div>

                    {/* The student module this was waiting for shipped; the
                        board is the screen it always meant to open. */}
                    <Btn asChild variant="primary">
                        <Link href={studentBoard.url(currentTeam.slug)}>
                            <PlusIcon />
                            Find Client
                        </Link>
                    </Btn>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '320px 1fr 1fr',
                        gap: 18,
                    }}
                >
                    <CalendarCard calendar={calendar} />
                    <ProgressCard project={project} />
                    <TeamCard project={project} />
                </div>

                <AnnouncementsCard announcement={announcement} />
            </div>
        </>
    );
}

function CalendarCard({ calendar }: { calendar: Props['calendar'] }) {
    return (
        <Panel padding="md" gap="md">
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <CalendarBlankIcon style={{ color: 'var(--color-accent)' }} />
                <span style={{ fontSize: 13, marginRight: 'auto' }}>
                    Calendar
                </span>
                <span style={{ fontSize: 12, color: MUTED(68) }}>
                    {calendar.label}
                </span>
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(7,1fr)',
                    gap: 3,
                    fontSize: 10,
                    letterSpacing: '0.06em',
                    color: MUTED(60),
                    textAlign: 'center',
                }}
            >
                {DAY_INITIALS.map((initial, index) => (
                    <span key={index}>{initial}</span>
                ))}
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(7,1fr)',
                    gap: 3,
                }}
            >
                {calendar.days.map((day) => (
                    <span
                        key={day.date}
                        data-day=""
                        data-today={day.isToday ? 'true' : undefined}
                        data-muted={day.isOutsideMonth ? 'true' : undefined}
                        title={day.milestone ?? undefined}
                        style={{
                            aspectRatio: '1 / 1',
                            display: 'grid',
                            placeItems: 'center',
                            fontSize: 11.5,
                            borderRadius: '50%',
                            position: 'relative',
                        }}
                    >
                        {day.day}
                        {day.milestone && (
                            <span
                                aria-label={day.milestone}
                                style={{
                                    position: 'absolute',
                                    bottom: 2,
                                    width: 4,
                                    height: 4,
                                    borderRadius: 2,
                                    background: 'var(--color-accent)',
                                }}
                            />
                        )}
                    </span>
                ))}
            </div>

            {calendar.days.every((day) => day.milestone === null) && (
                <span style={{ fontSize: 11, color: MUTED(55) }}>
                    Milestone dates appear here once an agreement is signed.
                </span>
            )}
        </Panel>
    );
}

function ProgressCard({ project }: { project: Props['project'] }) {
    const progress = project?.progress ?? null;
    const circumference = 2 * Math.PI * 58;

    return (
        <Panel
            padding="md"
            gap="sm"
            style={{ alignItems: 'center', justifyContent: 'center' }}
        >
            <div style={{ position: 'relative', width: 132, height: 132 }}>
                <svg
                    width={132}
                    height={132}
                    style={{ transform: 'rotate(-90deg)' }}
                >
                    <circle
                        cx={66}
                        cy={66}
                        r={58}
                        fill="none"
                        stroke="var(--color-divider)"
                        strokeWidth={9}
                    />
                    {progress !== null && (
                        <circle
                            cx={66}
                            cy={66}
                            r={58}
                            fill="none"
                            stroke="var(--color-accent)"
                            strokeWidth={9}
                            strokeLinecap="round"
                            strokeDasharray={circumference}
                            strokeDashoffset={
                                circumference * (1 - progress / 100)
                            }
                        />
                    )}
                </svg>
                <div
                    style={{
                        position: 'absolute',
                        inset: 0,
                        display: 'grid',
                        placeItems: 'center',
                        fontFamily: 'var(--font-heading)',
                        fontSize: 26,
                        opacity: progress === null ? 0.45 : 1,
                    }}
                >
                    {progress === null ? '—' : `${progress}%`}
                </div>
            </div>

            <div style={{ fontSize: 13.5 }}>Milestones approved</div>
            <div
                style={{
                    fontSize: 11,
                    color: MUTED(68),
                    textAlign: 'center',
                }}
            >
                {project === null
                    ? 'No active project yet'
                    : project.progress === null
                      ? `${project.title} · ${project.statusLabel} · awaiting a signed agreement`
                      : `${project.title}${project.dueDate ? ` · due ${project.dueDate}` : ''}`}
            </div>
        </Panel>
    );
}

function TeamCard({ project }: { project: Props['project'] }) {
    const currentTeam = useCurrentTeam();

    const team = project?.team ?? [];

    return (
        <Panel padding="md" gap="md">
            <div style={{ display: 'flex', alignItems: 'center' }}>
                <span style={{ fontSize: 13, marginRight: 'auto' }}>
                    Project team
                </span>
                {team.length > 0 && (
                    <Tag variant="accent">{team.length} active</Tag>
                )}
            </div>

            {team.length === 0 ? (
                <div style={{ fontSize: 11.5, color: MUTED(55) }}>
                    You are not on a project yet. Once a client accepts you,
                    your teammates show up here.
                </div>
            ) : (
                team.map((member) => (
                    <div
                        key={member.name}
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                        }}
                    >
                        <span
                            style={{
                                width: 34,
                                height: 34,
                                borderRadius: '50%',
                                background: 'var(--color-accent-800)',
                                display: 'grid',
                                placeItems: 'center',
                                color: 'var(--color-accent-200)',
                                flex: 'none',
                            }}
                        >
                            <UserIcon />
                        </span>
                        <div style={{ marginRight: 'auto', minWidth: 0 }}>
                            <div style={{ fontSize: 13 }}>{member.name}</div>
                            <div style={{ fontSize: 11, color: MUTED(68) }}>
                                {member.role ?? 'Student developer'}
                            </div>
                        </div>
                        <CircleIcon
                            weight="fill"
                            style={{
                                fontSize: 8,
                                color: member.isAvailable
                                    ? 'var(--color-accent)'
                                    : 'var(--color-neutral-700)',
                            }}
                        />
                    </div>
                ))
            )}

            {/*
             * The workspace is student/process — milestone tracking for the
             * build in hand. It was already built and reachable only by URL,
             * so this button is how anybody actually finds it. Without a
             * project there is nothing to open.
             */}
            <Btn
                asChild={project !== null}
                variant="secondary"
                block
                disabled={project === null}
                style={{ marginTop: 'auto' }}
                title={
                    project === null
                        ? 'Opens once you have been accepted onto a project.'
                        : undefined
                }
            >
                {project !== null ? (
                    <Link href={studentProcess.url(currentTeam.slug)}>
                        Open workspace
                    </Link>
                ) : (
                    'Open workspace'
                )}
            </Btn>
        </Panel>
    );
}

function AnnouncementsCard({
    announcement,
}: {
    announcement: Props['announcement'];
}) {
    return (
        <Panel padding="lg" gap="md" style={{ marginTop: 18 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <MegaphoneIcon style={{ color: 'var(--color-accent)' }} />
                <span style={{ fontSize: 13, marginRight: 'auto' }}>
                    News &amp; announcements
                </span>
                {announcement?.updatedAt && (
                    <span style={{ fontSize: 11, color: MUTED(68) }}>
                        Updated {announcement.updatedAt}
                    </span>
                )}
            </div>

            {announcement === null ? (
                <div style={{ fontSize: 12.5, color: MUTED(55) }}>
                    No announcements yet. Anything the administrators post
                    appears here.
                </div>
            ) : (
                <p
                    style={{
                        margin: 0,
                        fontSize: 12.5,
                        lineHeight: 1.55,
                        color: MUTED(80),
                        whiteSpace: 'pre-line',
                    }}
                >
                    {announcement.body}
                </p>
            )}
        </Panel>
    );
}
