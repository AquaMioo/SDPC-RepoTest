import { Link } from '@inertiajs/react';
import {
    ArrowSquareOutIcon,
    CalendarBlankIcon,
    CaretLeftIcon,
    CaretRightIcon,
    CheckCircleIcon,
    CircleIcon,
    ClockIcon,
    DotsThreeIcon,
    FlagIcon,
    StackIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';

import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

export type CurrentProject = {
    title: string;
    slug: string;
    reference: string;
    progress: number;
    approvedCount: number;
    milestoneCount: number;
    dueOn: string | null;
    currentPhase: string | null;
    nextMilestone: { title: string; dueOn: string | null } | null;
    milestones: {
        id: number;
        title: string;
        statusLabel: string;
        isDone: boolean;
    }[];
    updatedAt: string | null;
};

export type TeamMemberCard = {
    id: number;
    name: string;
    avatarUrl: string | null;
    role: string;
    /** Here now, as opposed to merely on the team. */
    isOnline: boolean;
};

export type CalendarEvent = {
    id: number;
    title: string;
    date: string | null;
    label: string | null;
    projectSlug: string;
    isDone: boolean;
};

/** The panel shell every card on this screen shares. */
function Card({
    children,
    className = '',
}: {
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <section
            className={`rounded-xl border border-[color-mix(in_srgb,var(--color-text)_9%,transparent)] bg-[var(--color-surface,#fff)] p-5 ${className}`}
        >
            {children}
        </section>
    );
}

/**
 * The global h6 rule upper-cases and letter-spaces its text, which is the
 * house style for panel kickers but not for these titles — the design reads
 * them in sentence case, so this is a plain element rather than a heading tag.
 */
function CardTitle({ children }: { children: React.ReactNode }) {
    return (
        <div className="text-[14.5px] font-medium tracking-normal normal-case">
            {children}
        </div>
    );
}

/* -------------------------------------------------------------- calendar */

const WEEKDAYS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

/**
 * A month grid with the milestone deadlines marked, and the next one written
 * out underneath. The month can be paged without leaving the screen.
 */
export function CalendarPanel({ events }: { events?: CalendarEvent[] }) {
    const today = new Date();
    const [offset, setOffset] = useState(0);

    const shown = new Date(today.getFullYear(), today.getMonth() + offset, 1);
    const year = shown.getFullYear();
    const month = shown.getMonth();

    const firstWeekday = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    const prefix = `${year}-${String(month + 1).padStart(2, '0')}`;
    const marked = new Set(
        (events ?? [])
            .map((event) => event.date)
            .filter((date): date is string => date !== null)
            .filter((date) => date.startsWith(prefix))
            .map((date) => Number(date.slice(8, 10))),
    );

    const isCurrentMonth =
        year === today.getFullYear() && month === today.getMonth();

    // Leading and trailing cells are the neighbouring months, greyed out, so
    // the grid always reads as six full weeks rather than a ragged edge.
    const lead = Array.from({ length: firstWeekday }, (_, index) => ({
        day: daysInPrevMonth - firstWeekday + index + 1,
        muted: true,
    }));
    const body = Array.from({ length: daysInMonth }, (_, index) => ({
        day: index + 1,
        muted: false,
    }));
    const trail = Array.from(
        { length: (7 - ((lead.length + body.length) % 7)) % 7 },
        (_, index) => ({ day: index + 1, muted: true }),
    );

    const upcoming = (events ?? []).find((event) => !event.isDone);

    return (
        <Card>
            <div className="mb-4 flex items-center gap-2">
                <CalendarBlankIcon className="text-[15px]" />
                <CardTitle>Calendar</CardTitle>
                <span className="ml-auto text-[12.5px] text-muted-foreground">
                    {shown.toLocaleString(undefined, {
                        month: 'short',
                        year: 'numeric',
                    })}
                </span>
                <button
                    type="button"
                    aria-label="Previous month"
                    className="grid size-5 place-items-center text-muted-foreground"
                    onClick={() => setOffset((value) => value - 1)}
                >
                    <CaretLeftIcon />
                </button>
                <button
                    type="button"
                    aria-label="Next month"
                    className="grid size-5 place-items-center text-muted-foreground"
                    onClick={() => setOffset((value) => value + 1)}
                >
                    <CaretRightIcon />
                </button>
            </div>

            <div className="grid grid-cols-7 gap-y-2 text-center">
                {WEEKDAYS.map((day, index) => (
                    <span
                        key={`${day}-${index}`}
                        className="text-[11.5px] text-muted-foreground"
                    >
                        {day}
                    </span>
                ))}

                {[...lead, ...body, ...trail].map((cell, index) => {
                    const isToday =
                        isCurrentMonth &&
                        !cell.muted &&
                        cell.day === today.getDate();
                    const hasEvent = !cell.muted && marked.has(cell.day);

                    return (
                        <span
                            key={index}
                            className="relative mx-auto grid size-7 place-items-center"
                        >
                            <span
                                className={[
                                    'grid size-7 place-items-center rounded-full text-[12.5px]',
                                    isToday
                                        ? 'bg-[var(--color-primary,#4a7c4e)] font-medium text-white'
                                        : cell.muted
                                          ? 'text-[color-mix(in_srgb,var(--color-text)_28%,transparent)]'
                                          : '',
                                ].join(' ')}
                            >
                                {cell.day}
                            </span>
                            {hasEvent && !isToday && (
                                <span className="absolute bottom-0 size-1 rounded-full bg-[var(--color-primary,#4a7c4e)]" />
                            )}
                            {hasEvent && isToday && (
                                <span className="absolute bottom-0.5 size-1 rounded-full bg-white" />
                            )}
                        </span>
                    );
                })}
            </div>

            {upcoming && (
                <div className="mt-5 border-t border-[color-mix(in_srgb,var(--color-text)_9%,transparent)] pt-4">
                    <div className="flex gap-2.5">
                        <ClockIcon className="mt-0.5 shrink-0 text-[14px] text-muted-foreground" />
                        <div className="min-w-0">
                            <div className="text-[13px]">{upcoming.title}</div>
                            <div className="text-[12px] text-muted-foreground">
                                Due {upcoming.label}
                            </div>
                            <Link
                                href={`#milestone-${upcoming.id}`}
                                className="mt-1 inline-block text-[12px] text-[var(--color-primary,#4a7c4e)]"
                            >
                                View schedule
                            </Link>
                        </div>
                    </div>
                </div>
            )}
        </Card>
    );
}

/* ------------------------------------------------------- project progress */

/**
 * The progress ring.
 *
 * An SVG rather than a chart library: it is one arc whose length is a
 * percentage, and a dependency for that would be a dependency to maintain.
 */
function ProgressRing({ value }: { value: number }) {
    const radius = 52;
    const circumference = 2 * Math.PI * radius;
    const filled = (Math.min(Math.max(value, 0), 100) / 100) * circumference;

    return (
        <div className="relative grid place-items-center">
            <svg width="136" height="136" viewBox="0 0 136 136" role="img">
                <title>{value}% complete</title>
                <circle
                    cx="68"
                    cy="68"
                    r={radius}
                    fill="none"
                    strokeWidth="11"
                    stroke="color-mix(in srgb, var(--color-text) 9%, transparent)"
                />
                <circle
                    cx="68"
                    cy="68"
                    r={radius}
                    fill="none"
                    strokeWidth="11"
                    strokeLinecap="round"
                    stroke="var(--color-primary,#4a7c4e)"
                    strokeDasharray={`${filled} ${circumference - filled}`}
                    transform="rotate(-90 68 68)"
                />
            </svg>
            <div className="absolute grid place-items-center text-center">
                <span className="text-[30px] leading-none font-semibold">
                    {value}%
                </span>
                <span className="mt-1 text-[11.5px] text-muted-foreground">
                    Complete
                </span>
            </div>
        </div>
    );
}

export function ProjectProgressPanel({
    project,
    href,
}: {
    project?: CurrentProject | null;
    href: string | null;
}) {
    if (project === undefined) {
        return (
            <Card>
                <CardTitle>Project progress</CardTitle>
                <div className="mt-5 grid place-items-center">
                    <Skeleton className="size-[136px] animate-pulse rounded-full" />
                </div>
                <Skeleton className="mt-5 h-4 w-2/3 animate-pulse" />
                <Skeleton className="mt-2 h-4 w-1/2 animate-pulse" />
            </Card>
        );
    }

    if (project === null) {
        return (
            <Card>
                <CardTitle>Project progress</CardTitle>
                <p className="mt-3 mb-0 text-[13px] leading-relaxed text-muted-foreground">
                    Progress appears once a student has signed an agreement for
                    one of your postings. Until then there are no milestones to
                    track.
                </p>
            </Card>
        );
    }

    return (
        <Card>
            <div className="mb-4 flex items-center">
                <CardTitle>Project progress</CardTitle>
                {href && (
                    <Link
                        href={href}
                        aria-label="Open the posting"
                        className="ml-auto text-muted-foreground"
                    >
                        <DotsThreeIcon weight="bold" />
                    </Link>
                )}
            </div>

            <div className="grid place-items-center">
                <ProgressRing value={project.progress} />
                <div className="mt-4 text-center">
                    {/*
                     * Spelled out, because a bare percentage invites you to
                     * read it as "how much of the work is done". It is not
                     * that — it is how many milestones the client has signed
                     * off, which is the only part anybody actually recorded.
                     */}
                    <div className="text-[12px] text-muted-foreground">
                        {project.approvedCount} of {project.milestoneCount}{' '}
                        milestone
                        {project.milestoneCount === 1 ? '' : 's'} approved
                    </div>
                    <div className="mt-1 text-[14.5px] font-medium">
                        {project.title}
                    </div>
                    {project.dueOn && (
                        <div className="text-[12px] text-muted-foreground">
                            Due: {project.dueOn}
                        </div>
                    )}
                </div>
            </div>

            {(project.currentPhase || project.nextMilestone) && (
                <div className="mt-4 grid gap-4 rounded-lg bg-[color-mix(in_srgb,var(--color-text)_4%,transparent)] p-3.5 sm:grid-cols-2">
                    {project.currentPhase && (
                        <div className="flex gap-2">
                            <StackIcon className="mt-0.5 shrink-0 text-[15px] text-muted-foreground" />
                            <div className="min-w-0">
                                <div className="text-[11.5px] text-muted-foreground">
                                    Current phase
                                </div>
                                <div className="truncate text-[13px]">
                                    {project.currentPhase}
                                </div>
                            </div>
                        </div>
                    )}
                    {project.nextMilestone && (
                        <div className="flex gap-2">
                            <FlagIcon className="mt-0.5 shrink-0 text-[15px] text-muted-foreground" />
                            <div className="min-w-0">
                                <div className="text-[11.5px] text-muted-foreground">
                                    Next milestone
                                </div>
                                <div className="truncate text-[13px]">
                                    {project.nextMilestone.title}
                                </div>
                                {project.nextMilestone.dueOn && (
                                    <div className="text-[11.5px] text-muted-foreground">
                                        Due: {project.nextMilestone.dueOn}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            )}

            <div className="mt-5">
                <div className="mb-2.5 text-[12px] text-muted-foreground">
                    Milestones
                </div>
                <div className="grid gap-2.5">
                    {project.milestones.map((milestone) => (
                        <div
                            key={milestone.id}
                            id={`milestone-${milestone.id}`}
                            className="flex items-center gap-2.5"
                        >
                            <span
                                className={
                                    milestone.isDone
                                        ? 'shrink-0 text-[15px] text-[var(--color-primary,#4a7c4e)]'
                                        : 'shrink-0 text-[15px] text-muted-foreground'
                                }
                                aria-hidden="true"
                            >
                                {milestone.isDone ? (
                                    <CheckCircleIcon weight="fill" />
                                ) : (
                                    <CircleIcon />
                                )}
                            </span>
                            <span className="min-w-0 flex-1 truncate text-[12.5px]">
                                {milestone.title}
                            </span>
                            {/*
                             * The status somebody recorded, not a bar. A
                             * half-filled bar for "in progress" was a
                             * measurement nobody took.
                             */}
                            <span className="shrink-0 text-[12px] text-muted-foreground">
                                {milestone.statusLabel}
                            </span>
                        </div>
                    ))}
                </div>
            </div>

            <div className="mt-5 flex items-center gap-2 border-t border-[color-mix(in_srgb,var(--color-text)_9%,transparent)] pt-3.5 text-[12px] text-muted-foreground">
                {project.updatedAt && (
                    <>
                        <ClockIcon className="text-[13px]" />
                        <span>Last updated: {project.updatedAt}</span>
                    </>
                )}
                {href && (
                    <Link
                        href={href}
                        className="ml-auto text-[var(--color-primary,#4a7c4e)]"
                    >
                        View full progress →
                    </Link>
                )}
            </div>
        </Card>
    );
}

/* ----------------------------------------------------------- project team */

export function ProjectTeamPanel({
    members,
    workspaceHref,
}: {
    members?: TeamMemberCard[];
    workspaceHref: string | null;
}) {
    const online = (members ?? []).filter((member) => member.isOnline).length;

    if (members === undefined) {
        return (
            <Card>
                <CardTitle>Project team</CardTitle>
                <Skeleton className="mt-4 h-10 w-full animate-pulse" />
                <Skeleton className="mt-2 h-10 w-full animate-pulse" />
            </Card>
        );
    }

    return (
        <Card className="flex flex-col">
            <div className="mb-4 flex items-center">
                <CardTitle>Project team</CardTitle>
                {online > 0 && (
                    <span className="ml-auto rounded-full bg-[color-mix(in_srgb,var(--color-primary,#4a7c4e)_12%,transparent)] px-2.5 py-1 text-[11.5px] text-[var(--color-primary,#4a7c4e)]">
                        {online} active
                    </span>
                )}
            </div>

            {members.length === 0 && (
                <p className="m-0 text-[13px] leading-relaxed text-muted-foreground">
                    Students you accept onto a posting appear here.
                </p>
            )}

            <div className="grid gap-4">
                {members.map((member) => (
                    <div key={member.id} className="flex items-center gap-3">
                        <span className="grid size-9 shrink-0 place-items-center overflow-hidden rounded-full bg-[color-mix(in_srgb,var(--color-primary,#4a7c4e)_12%,transparent)] text-[13px] text-[var(--color-primary,#4a7c4e)]">
                            {member.avatarUrl ? (
                                <img
                                    src={member.avatarUrl}
                                    alt=""
                                    className="size-full object-cover"
                                />
                            ) : (
                                member.name.charAt(0).toUpperCase()
                            )}
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="truncate text-[13.5px] font-medium">
                                {member.name}
                            </div>
                            <div className="truncate text-[12px] text-muted-foreground">
                                {member.role}
                            </div>
                            {/*
                             * This said "Active" for everyone, always — a
                             * hardcoded word, not a reading of anything. A
                             * student who had signed out still showed a green
                             * dot on their client's dashboard.
                             */}
                            <div className="mt-0.5 flex items-center gap-1.5 text-[11.5px] text-muted-foreground">
                                <span
                                    className={cn(
                                        'size-1.5 rounded-full',
                                        member.isOnline
                                            ? 'bg-[var(--color-primary,#4a7c4e)]'
                                            : 'bg-[color-mix(in_srgb,var(--color-text)_28%,transparent)]',
                                    )}
                                />
                                {member.isOnline ? 'Active' : 'Away'}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {workspaceHref && members.length > 0 && (
                <Link
                    href={workspaceHref}
                    className="mt-auto flex items-center justify-center gap-2 rounded-lg border border-[color-mix(in_srgb,var(--color-text)_12%,transparent)] px-3 py-2.5 text-[13px]"
                >
                    Open workspace <ArrowSquareOutIcon />
                </Link>
            )}
        </Card>
    );
}

/* --------------------------------------------------------- announcements */

export type Announcement = {
    body: string;
    updatedAt: string | null;
};

/**
 * The announcements strip.
 *
 * Shows the block an administrator maintains on the admin Content screen —
 * the same row the student dashboard reads, so one piece of copy reaches both
 * modules instead of two that drift apart.
 */
export function AnnouncementsPanel({
    announcement,
}: {
    announcement: Announcement | null;
}) {
    return (
        <Card>
            <div className="flex items-baseline gap-3">
                <CardTitle>News &amp; announcements</CardTitle>
                {announcement?.updatedAt && (
                    <span className="ml-auto text-[11.5px] text-muted-foreground">
                        Updated {announcement.updatedAt}
                    </span>
                )}
            </div>

            {announcement === null ? (
                <p className="mt-2 mb-0 text-[12.5px] text-muted-foreground">
                    Nothing posted yet.
                </p>
            ) : (
                /*
                 * The administrator writes plain text, so newlines are the
                 * only formatting there is to honour.
                 */
                <p className="mt-3 mb-0 text-[13px] leading-relaxed whitespace-pre-line">
                    {announcement.body}
                </p>
            )}
        </Card>
    );
}
