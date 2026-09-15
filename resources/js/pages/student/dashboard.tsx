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
import { UpcomingMeetingsPanel } from '@/components/sdpc/upcoming-meetings';
import type { UpcomingMeeting } from '@/components/sdpc/upcoming-meetings';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { localDateKey, whenLabel } from '@/lib/meeting-time';
import { projectManagement } from '@/routes';
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
        /** Tasks the client verified over every task; null before signing. */
        progress: number | null;
        verifiedCount: number | null;
        submittedCount: number | null;
        taskCount: number | null;
        currentPhase: string | null;
        nextMilestone: { title: string; dueOn: string | null } | null;
        phases: {
            id: number;
            title: string;
            progress: number;
            isDone: boolean;
        }[];
        team: { name: string; role: string | null; isAvailable: boolean }[];
    } | null;
    calendar: { label: string; days: CalendarDay[] };
    announcement: { body: string; updatedAt: string | null } | null;
    pendingInvitations?: DashboardInvitation[];
    /** Meetings booked in this student's threads, soonest first. */
    upcomingMeetings?: UpcomingMeeting[];
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
    upcomingMeetings = [],
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
                    maxWidth: 'clamp(1320px, 100vw - 320px, 1600px)',
                    margin: '0 auto',
                    padding: '30px clamp(16px, 4vw, 32px) 72px',
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

                {/*
                 * One card per row on a phone, two once there is room, and the
                 * design's calendar-plus-two only on a desktop. Three fixed
                 * columns at 375px put the whole row past the viewport edge.
                 */}
                <div className="grid gap-[18px] sm:grid-cols-2 lg:grid-cols-[320px_1fr_1fr]">
                    <CalendarCard
                        calendar={calendar}
                        meetings={upcomingMeetings}
                    />
                    <ProgressCard project={project} />
                    <TeamCard project={project} />
                </div>

                <div style={{ marginTop: 18 }}>
                    <UpcomingMeetingsPanel meetings={upcomingMeetings} />
                </div>

                <AnnouncementsCard announcement={announcement} />
            </div>
        </>
    );
}

/**
 * The month, and what the agreement has scheduled in it.
 *
 * A day is a button rather than a span: the milestone used to be reachable
 * only through the `title` tooltip, which a touch screen never shows and a
 * keyboard never reaches. Selecting a day writes it into the line under the
 * grid, so every day says what it holds without a pointer hovering over it.
 */
function CalendarCard({
    calendar,
    meetings,
}: {
    calendar: Props['calendar'];
    meetings: UpcomingMeeting[];
}) {
    const [selected, setSelected] = useState<string | null>(null);
    const selectedDay =
        calendar.days.find((day) => day.date === selected) ?? null;

    /*
     * Meetings keyed on the viewer's own calendar day. The cells come from the
     * server, but a meeting's day has to be read in the browser's time zone —
     * see localDateKey.
     */
    const meetingsOn = new Map<string, string[]>();

    for (const meeting of meetings) {
        const key = localDateKey(meeting.scheduledAt);
        const line = `${whenLabel(meeting.scheduledAt)} with ${meeting.with}`;

        meetingsOn.set(key, [...(meetingsOn.get(key) ?? []), line]);
    }

    /** Everything on a day: its milestone, then any meetings. */
    const describe = (day: CalendarDay): string | null => {
        const parts = [
            ...(day.milestone === null ? [] : [day.milestone]),
            ...(meetingsOn.get(day.date) ?? []),
        ];

        return parts.length === 0 ? null : parts.join(' · ');
    };

    const nothingScheduled = calendar.days.every(
        (day) => describe(day) === null,
    );

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
                    gridTemplateColumns: 'repeat(7, minmax(0, 1fr))',
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
                    gridTemplateColumns: 'repeat(7, minmax(0, 1fr))',
                    gap: 3,
                }}
            >
                {calendar.days.map((day) => {
                    const isSelected = day.date === selected;

                    return (
                        <button
                            key={day.date}
                            type="button"
                            data-day=""
                            data-today={day.isToday ? 'true' : undefined}
                            data-muted={day.isOutsideMonth ? 'true' : undefined}
                            aria-pressed={isSelected}
                            aria-label={
                                describe(day) === null
                                    ? day.date
                                    : `${day.date} — ${describe(day)}`
                            }
                            onClick={() =>
                                setSelected(isSelected ? null : day.date)
                            }
                            /* The button reset, the hover tint and the
                               selection ring live in nocturne.css: an inline
                               background here would outrank the today pill. */
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
                            {describe(day) !== null && (
                                <span
                                    aria-hidden="true"
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
                        </button>
                    );
                })}
            </div>

            {selectedDay !== null ? (
                <span style={{ fontSize: 11, color: MUTED(70) }}>
                    {describe(selectedDay) ?? 'Nothing scheduled for this day.'}
                </span>
            ) : (
                nothingScheduled && (
                    <span style={{ fontSize: 11, color: MUTED(55) }}>
                        Milestones and booked meetings appear here.
                    </span>
                )
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

            {/*
             * Named for what it counts: tasks the client verified. Checking a
             * task off does not move this — only the client's verification
             * does — so it is never the student's own estimate.
             */}
            <div style={{ fontSize: 13.5 }}>
                {project?.taskCount
                    ? `${project.verifiedCount} of ${project.taskCount} tasks verified`
                    : 'Tasks verified'}
            </div>
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

            {project !== null && project.progress !== null && (
                <div
                    style={{
                        width: '100%',
                        display: 'grid',
                        gap: 10,
                        marginTop: 6,
                    }}
                >
                    {(project.currentPhase || project.nextMilestone) && (
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    'repeat(auto-fit, minmax(110px, 1fr))',
                                gap: 8,
                                padding: '8px 10px',
                                borderRadius: 'var(--radius-md)',
                                background: MUTED(5),
                                fontSize: 11,
                            }}
                        >
                            {project.currentPhase && (
                                <div>
                                    <div style={{ color: MUTED(55) }}>
                                        Current phase
                                    </div>
                                    <div style={{ fontSize: 12.5 }}>
                                        {project.currentPhase}
                                    </div>
                                </div>
                            )}
                            {project.nextMilestone && (
                                <div>
                                    <div style={{ color: MUTED(55) }}>
                                        Next milestone
                                    </div>
                                    <div style={{ fontSize: 12.5 }}>
                                        {project.nextMilestone.title}
                                    </div>
                                    {project.nextMilestone.dueOn && (
                                        <div style={{ color: MUTED(55) }}>
                                            Due {project.nextMilestone.dueOn}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {project.phases.map((phase) => (
                        <div
                            key={phase.id}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                fontSize: 11.5,
                            }}
                        >
                            <span style={{ width: 64, flex: 'none' }}>
                                {phase.title}
                            </span>
                            <span
                                role="progressbar"
                                aria-valuenow={phase.progress}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-label={`${phase.title} verified`}
                                style={{
                                    flex: 1,
                                    height: 5,
                                    borderRadius: 3,
                                    background: 'var(--color-divider)',
                                    overflow: 'hidden',
                                }}
                            >
                                <span
                                    style={{
                                        display: 'block',
                                        width: `${phase.progress}%`,
                                        height: '100%',
                                        background: 'var(--color-accent)',
                                    }}
                                />
                            </span>
                            <span
                                style={{
                                    width: 32,
                                    textAlign: 'right',
                                    color: MUTED(65),
                                }}
                            >
                                {phase.progress}%
                            </span>
                        </div>
                    ))}
                </div>
            )}
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
             * The workspace is Project Management — the checklist and timeline
             * for the build in hand. Without a project there is nothing to
             * open.
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
                    <Link href={projectManagement.url(currentTeam.slug)}>
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
