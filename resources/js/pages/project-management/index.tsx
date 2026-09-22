import { Head, Link, router } from '@inertiajs/react';
import { FlagCheckeredIcon, LockSimpleIcon } from '@phosphor-icons/react';
import { useState } from 'react';
import { toast } from 'sonner';

import { ApplicationsSection } from '@/components/project-management/applications-section';
import { PhaseChecklist } from '@/components/project-management/phase-checklist';
import { PhaseTimeline } from '@/components/project-management/phase-timeline';
import {
    IN_PLACE,
    ScheduleDialog,
} from '@/components/project-management/task-dialogs';
import type {
    ManagedAgreement,
    Phase,
    ProjectManagementProps,
} from '@/components/project-management/types';
import { Btn } from '@/components/sdpc/btn';
import { Select } from '@/components/sdpc/input';
import { Panel } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { shortDate } from '@/lib/calendar-days';
import { projectManagement } from '@/routes';
import { show as agreementShow } from '@/routes/agreements';
import { schedule as scheduleMilestone } from '@/routes/agreements/milestones';
import { index as projectsIndex } from '@/routes/projects';
import { index as boardIndex } from '@/routes/student/board';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const STATE_TAG: Record<
    Phase['state'],
    { label: string; variant: 'outline' | 'neutral' | 'accent' }
> = {
    in_progress: { label: 'In progress', variant: 'outline' },
    upcoming: { label: 'Upcoming', variant: 'neutral' },
    done: { label: 'Done', variant: 'accent' },
};

/**
 * "Project Management" — where a signed build is tracked, by both sides.
 *
 * Replaces the student's Workflow and the client's Project Process tabs. The
 * student is the updater: they write each phase's checklist, plan the timeline
 * and check tasks off with proof. The client is the verifier: only they can
 * mark a task done. Progress is the share of tasks the client verified, and it
 * is worked out on the server — nothing on this screen lets anyone type it.
 *
 * Locked until the two sides are collaborating (a signed, active agreement).
 */
export default function ProjectManagement({
    side,
    agreements,
    agreement,
    completedAgreements,
    can,
    isLocked,
    pendingAgreementId,
    applications,
}: ProjectManagementProps) {
    const team = useCurrentTeam();
    const [scheduling, setScheduling] = useState<Phase | null>(null);

    return (
        <>
            <Head title="Project Management" />

            <div
                className="page-shell"
                data-motion=""
                style={{
                    maxWidth: 'clamp(1320px, 100vw - 320px, 1600px)',
                    margin: '0 auto',
                    paddingTop: 30,
                    paddingBottom: 72,
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 18,
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        gap: 12,
                        flexWrap: 'wrap',
                    }}
                >
                    <div style={{ marginRight: 'auto', minWidth: 0 }}>
                        <div style={{ fontSize: 12, color: MUTED(55) }}>
                            Project Management
                        </div>
                        <h3 style={{ margin: 0 }}>
                            {agreement?.projectTitle ?? 'Project Management'}
                        </h3>
                        <div style={{ fontSize: 13, color: MUTED(60) }}>
                            {agreement === null
                                ? 'Track your project here once you start working together.'
                                : side === 'student'
                                  ? 'Mark a task done and attach proof. It stays pending until the client checks and approves it.'
                                  : `When ${agreement.studentName} marks a task done, it waits here for you to check. Only tasks you approve count toward progress.`}
                        </div>
                    </div>

                    {agreements.length > 1 && (
                        <Select
                            aria-label="Choose a project"
                            value={agreement?.id ?? ''}
                            onChange={(event) =>
                                router.get(
                                    projectManagement.url(team.slug, {
                                        query: {
                                            agreement: event.target.value,
                                        },
                                    }),
                                )
                            }
                            style={{ width: 'auto', maxWidth: '100%' }}
                        >
                            {agreements.map((each) => (
                                <option key={each.id} value={each.id}>
                                    {each.projectTitle} · {each.counterpart}
                                </option>
                            ))}
                        </Select>
                    )}

                    {/* Only once there is an agreement. Before that the locked
                        panel below carries the same link, and three buttons to
                        one page read as three different things (QA). A student
                        tied to a build is offered no way to more work: they
                        cannot take any until the client completes it. */}
                    {agreement !== null &&
                        (side === 'client' ? (
                            <Btn asChild variant="secondary">
                                <Link href={projectsIndex.url(team.slug)}>
                                    Your postings
                                </Link>
                            </Btn>
                        ) : (
                            !isLocked && (
                                <Btn asChild variant="secondary">
                                    <Link href={boardIndex.url(team.slug)}>
                                        Find more work
                                    </Link>
                                </Btn>
                            )
                        ))}

                    {agreement !== null && (
                        <Tag
                            variant="outline"
                            title={`${agreement.summary.verifiedCount} of ${agreement.summary.taskCount} tasks verified`}
                        >
                            {agreement.summary.progress}% overall
                        </Tag>
                    )}
                </div>

                {agreement === null ? (
                    <LockedPanel
                        side={side}
                        teamSlug={team.slug}
                        pendingAgreementId={pendingAgreementId}
                        isLocked={isLocked}
                    />
                ) : (
                    <Workspace
                        agreement={agreement}
                        teamSlug={team.slug}
                        canManage={can.manage}
                        canVerify={can.verify}
                        canComplete={can.complete}
                        onEditDates={setScheduling}
                    />
                )}

                {/* Like "Your postings" for a client with a build running: a
                    student on one has no applications to manage. */}
                {side === 'student' && !isLocked && (
                    <ApplicationsSection
                        applications={applications}
                        teamSlug={team.slug}
                    />
                )}

                {completedAgreements.length > 0 && (
                    <CompletedProjects
                        projects={completedAgreements}
                        selectedId={agreement?.id ?? null}
                        teamSlug={team.slug}
                    />
                )}
            </div>

            {agreement !== null && scheduling !== null && (
                <ScheduleDialog
                    open
                    onOpenChange={(open) => !open && setScheduling(null)}
                    url={scheduleMilestone.url({
                        current_team: team.slug,
                        agreement: agreement.id,
                        milestone: scheduling.id,
                    })}
                    phase={scheduling}
                />
            )}
        </>
    );
}

function Workspace({
    agreement,
    teamSlug,
    canManage,
    canVerify,
    canComplete,
    onEditDates,
}: {
    agreement: ManagedAgreement;
    teamSlug: string;
    canManage: boolean;
    canVerify: boolean;
    canComplete: boolean;
    onEditDates: (phase: Phase) => void;
}) {
    const { summary } = agreement;
    const daysLeft = summary.daysToFinalDeadline;

    /*
     * Dragging a bar moves it at once and asks the server afterwards: a bar
     * that snaps back until the round trip lands reads as a failed drag.
     * Inertia puts the old dates back itself if the server refuses.
     */
    const reschedule = (phase: Phase, startsOn: string, endsOn: string) => {
        router
            .optimistic<{ agreement: ManagedAgreement | null }>((props) =>
                props.agreement === null
                    ? {}
                    : {
                          agreement: {
                              ...props.agreement,
                              phases: props.agreement.phases.map((each) =>
                                  each.id === phase.id
                                      ? {
                                            ...each,
                                            startsOn,
                                            endsOn,
                                            isRescheduled: true,
                                        }
                                      : each,
                              ),
                          },
                      },
            )
            .patch(
                scheduleMilestone.url({
                    current_team: teamSlug,
                    agreement: agreement.id,
                    milestone: phase.id,
                }),
                { starts_on: startsOn, ends_on: endsOn },
                {
                    ...IN_PLACE,
                    onError: (errors) => {
                        const message = Object.values(errors)[0];

                        if (message) {
                            toast.error(message);
                        }
                    },
                },
            );
    };

    return (
        <>
            {agreement.isCompleted && (
                <Panel
                    padding="lg"
                    gap="sm"
                    className="pm-reveal"
                    style={{
                        flexDirection: 'row',
                        alignItems: 'center',
                        gap: 12,
                        background:
                            'color-mix(in srgb, var(--color-accent) 9%, transparent)',
                    }}
                >
                    <FlagCheckeredIcon style={{ fontSize: 20, flex: 'none' }} />
                    <span style={{ fontSize: 13.5 }}>
                        Completed
                        {agreement.completedOn
                            ? ` on ${agreement.completedOn}`
                            : ''}
                        . This is the record of what was delivered, so nothing
                        here can be changed.
                    </span>
                </Panel>
            )}

            <div
                style={{
                    display: 'flex',
                    gap: 16,
                    flexWrap: 'wrap',
                    fontSize: 12.5,
                    color: MUTED(65),
                }}
            >
                <span>
                    <strong style={{ color: 'var(--color-text)' }}>
                        {summary.verifiedCount} of {summary.taskCount}
                    </strong>{' '}
                    tasks verified
                </span>
                {summary.finalDeadline && !agreement.isCompleted && (
                    <span
                        style={{
                            color:
                                daysLeft !== null && daysLeft < 0
                                    ? 'var(--destructive)'
                                    : undefined,
                        }}
                    >
                        Final deadline {shortDate(summary.finalDeadline)}
                        {daysLeft === null
                            ? ''
                            : daysLeft > 1
                              ? ` · ${daysLeft} days left`
                              : daysLeft === 1
                                ? ' · 1 day left'
                                : daysLeft === 0
                                  ? ' · today'
                                  : ` · ${Math.abs(daysLeft)} days over`}
                    </span>
                )}
                {summary.overdueTaskCount > 0 && !agreement.isCompleted && (
                    <span style={{ color: 'var(--destructive)' }}>
                        {summary.overdueTaskCount} overdue
                    </span>
                )}
                {summary.submittedCount > 0 && (
                    <span>{summary.submittedCount} pending client review</span>
                )}
                {summary.currentPhase && (
                    <span>Current phase: {summary.currentPhase.title}</span>
                )}
                {summary.nextMilestone && (
                    <span>
                        Next: {summary.nextMilestone.title}
                        {summary.nextMilestone.dueOn
                            ? ` · due ${summary.nextMilestone.dueOn}`
                            : ''}
                    </span>
                )}
                <span style={{ marginLeft: 'auto' }}>
                    {agreement.reference} · with {agreement.counterpart}
                </span>
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(190px, 1fr))',
                    gap: 12,
                }}
            >
                {agreement.phases.map((phase) => (
                    <Panel key={phase.id} padding="lg" gap="sm">
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'flex-start',
                                gap: 8,
                            }}
                        >
                            <span
                                style={{ fontSize: 13.5, marginRight: 'auto' }}
                            >
                                {phase.title}
                            </span>
                            <Tag variant={STATE_TAG[phase.state].variant}>
                                {STATE_TAG[phase.state].label}
                            </Tag>
                        </div>
                        <span style={{ fontSize: 11.5, color: MUTED(58) }}>
                            {shortDate(phase.startsOn)} –{' '}
                            {shortDate(phase.endsOn)}
                        </span>
                        <div
                            role="progressbar"
                            aria-valuenow={phase.progress}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label={`${phase.title} verified`}
                            style={{
                                height: 4,
                                borderRadius: 2,
                                background: MUTED(10),
                                overflow: 'hidden',
                            }}
                        >
                            <div
                                style={{
                                    width: `${phase.progress}%`,
                                    height: '100%',
                                    background: 'var(--color-accent)',
                                }}
                            />
                        </div>
                        <span style={{ fontSize: 11, color: MUTED(55) }}>
                            {phase.verifiedCount} of {phase.taskCount} tasks
                            verified
                        </span>
                    </Panel>
                ))}
            </div>

            <PhaseTimeline
                phases={agreement.phases}
                canEdit={canManage}
                onReschedule={reschedule}
                onEditDates={onEditDates}
            />

            {agreement.phases.map((phase) => (
                <PhaseChecklist
                    key={phase.id}
                    phase={phase}
                    teamSlug={teamSlug}
                    agreementId={agreement.id}
                    canManage={canManage}
                    canVerify={canVerify}
                    finalDeadline={agreement.finalDeadline}
                    finalDeadlineRequest={agreement.finalDeadlineRequest}
                    completion={
                        phase.isTurnover && canComplete
                            ? {
                                  projectTitle: agreement.projectTitle,
                                  unverifiedCount:
                                      summary.taskCount - summary.verifiedCount,
                              }
                            : null
                    }
                />
            ))}
        </>
    );
}

/**
 * Builds the client completed, kept as the record of what was delivered.
 */
function CompletedProjects({
    projects,
    selectedId,
    teamSlug,
}: {
    projects: ProjectManagementProps['completedAgreements'];
    selectedId: number | null;
    teamSlug: string;
}) {
    return (
        <Panel padding="lg" gap="sm" className="pm-reveal">
            <span
                style={{
                    fontSize: 13,
                    fontWeight: 600,
                    letterSpacing: '0.04em',
                    textTransform: 'uppercase',
                }}
            >
                Completed projects
            </span>
            <div style={{ display: 'grid', gap: 6 }}>
                {projects.map((project) => (
                    <Link
                        key={project.id}
                        href={projectManagement.url(teamSlug, {
                            query: { agreement: project.id },
                        })}
                        preserveScroll={false}
                        data-quiet=""
                        className="pm-task"
                        aria-current={
                            project.id === selectedId ? 'page' : undefined
                        }
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            flexWrap: 'wrap',
                        }}
                    >
                        <FlagCheckeredIcon style={{ flex: 'none' }} />
                        <span style={{ fontSize: 13, marginRight: 'auto' }}>
                            {project.projectTitle} · {project.counterpart}
                        </span>
                        <span style={{ fontSize: 11.5, color: MUTED(58) }}>
                            {project.reference}
                            {project.completedOn
                                ? ` · completed ${project.completedOn}`
                                : ''}
                        </span>
                    </Link>
                ))}
            </div>
        </Panel>
    );
}

function LockedPanel({
    side,
    teamSlug,
    pendingAgreementId,
    isLocked,
}: {
    side: 'student' | 'client';
    teamSlug: string;
    pendingAgreementId: number | null;
    /** A student already taken on, waiting for the agreement to be signed. */
    isLocked: boolean;
}) {
    return (
        <Panel padding="lg" gap="md" style={{ alignItems: 'flex-start' }}>
            <span
                style={{
                    width: 38,
                    height: 38,
                    borderRadius: '50%',
                    display: 'grid',
                    placeItems: 'center',
                    background: MUTED(8),
                    color: MUTED(70),
                    fontSize: 18,
                }}
            >
                <LockSimpleIcon />
            </span>
            <div style={{ fontSize: 15 }}>
                Project Management opens when your project starts
            </div>
            <div
                style={{
                    fontSize: 13,
                    color: MUTED(62),
                    maxWidth: 620,
                    lineHeight: 1.55,
                }}
            >
                {side === 'student'
                    ? 'It opens once a client accepts you and you both sign the agreement. Then you can list the tasks for each phase, plan your timeline, and mark work done for the client to check.'
                    : 'It opens once you accept a student and you both sign the agreement. The student then lists the tasks and marks work done, and you check and approve it here.'}
            </div>
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                {pendingAgreementId !== null && (
                    <Btn asChild variant="primary">
                        <Link
                            href={agreementShow.url({
                                current_team: teamSlug,
                                agreement: pendingAgreementId,
                            })}
                        >
                            Open the agreement to sign
                        </Link>
                    </Btn>
                )}
                {side === 'client' ? (
                    <Btn asChild variant="secondary">
                        <Link href={projectsIndex.url(teamSlug)}>
                            Your postings
                        </Link>
                    </Btn>
                ) : (
                    !isLocked && (
                        <Btn asChild variant="secondary">
                            <Link href={boardIndex.url(teamSlug)}>
                                Find clients
                            </Link>
                        </Btn>
                    )
                )}
            </div>
        </Panel>
    );
}
