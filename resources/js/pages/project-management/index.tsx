import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowsOutIcon,
    CaretRightIcon,
    FlagCheckeredIcon,
    LockSimpleIcon,
} from '@phosphor-icons/react';
import type { ReactNode } from 'react';
import { Fragment, useState } from 'react';
import { toast } from 'sonner';

import {
    ContractRequestBanner,
    TerminationButton,
} from '@/components/agreements/contract-requests';
import { PaymentDueDialog } from '@/components/agreements/payment-due-dialog';
import { ApplicationsSection } from '@/components/project-management/applications-section';
import {
    PhaseChecklist,
    PhaseNumber,
} from '@/components/project-management/phase-checklist';
import { PhaseTimeline } from '@/components/project-management/phase-timeline';
import {
    IN_PLACE,
    ScheduleDialog,
} from '@/components/project-management/task-dialogs';
import type {
    ManagedAgreement,
    Phase,
    ProjectExtension,
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
import { store as terminationsStore } from '@/routes/agreements/terminations';
import { index as projectsIndex } from '@/routes/projects';
import { index as recruitIndex } from '@/routes/recruit';
import { index as boardIndex } from '@/routes/student/board';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/** What the open project extension is waiting on, for the side reading it. */
function extensionNotice(
    open: NonNullable<ProjectExtension['open']>,
    isStudentSide: boolean,
): string {
    if (open.status === 'draft' || open.status === 'awaiting_signatures') {
        return isStudentSide
            ? 'The client wants to extend this project. Add to the addendum’s Section II, agree on the amount, then sign it.'
            : 'Your project extension addendum is waiting to be filled in and signed by both sides.';
    }

    if (!open.downPaymentPaid) {
        return isStudentSide
            ? 'The extension addendum is signed. The extended work starts once the client’s down payment clears.'
            : 'The extension addendum is signed. Pay Milestone 1, the down payment, to start the extended work.';
    }

    return isStudentSide
        ? 'Down payment received: the extended services are in Objective & Scope below. The final balance is due once you hand them all in.'
        : 'The extended services are in Objective & Scope below. Their files unlock once you pay the final balance.';
}

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
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 6,
                                flexWrap: 'wrap',
                                fontSize: 12,
                                color: MUTED(55),
                            }}
                        >
                            <span>Project Management</span>
                            {agreement !== null && (
                                <>
                                    <CaretRightIcon aria-hidden="true" />
                                    <span>
                                        {agreement.reference} · with{' '}
                                        {agreement.counterpart}
                                    </span>
                                </>
                            )}
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

                    {/* No collaboration yet: the way to start one sits up here. */}
                    {agreement === null &&
                        (side === 'client' ? (
                            <>
                                {/* Every posting: open, archived, rejected (owner, 2026-10-20). */}
                                <Btn asChild variant="secondary">
                                    <Link href={projectsIndex.url(team.slug)}>
                                        Posting
                                    </Link>
                                </Btn>
                                <Btn asChild variant="secondary">
                                    <Link href={recruitIndex.url(team.slug)}>
                                        Hire student
                                    </Link>
                                </Btn>
                            </>
                        ) : (
                            !isLocked && (
                                <Btn asChild variant="secondary">
                                    <Link href={boardIndex.url(team.slug)}>
                                        Find clients
                                    </Link>
                                </Btn>
                            )
                        ))}
                </div>

                {agreement === null ? (
                    <LockedPanel
                        side={side}
                        teamSlug={team.slug}
                        pendingAgreementId={pendingAgreementId}
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

            {/* A request to end the build early, waiting for an answer. */}
            {agreement.termination && (
                <ContractRequestBanner
                    request={agreement.termination}
                    teamSlug={teamSlug}
                    agreementId={agreement.id}
                />
            )}

            {/* The milestone due now, announced once to each side (owner, 2026-10-18). */}
            {agreement.extension.open && (
                <PaymentDueDialog
                    addendumId={agreement.extension.open.id}
                    reference={agreement.extension.open.reference}
                    stage={
                        agreement.extension.open.status !== 'active'
                            ? null
                            : !agreement.extension.open.downPaymentPaid
                              ? 'down'
                              : agreement.extension.open.workHandedIn &&
                                  !agreement.extension.open.finalPaid
                                ? 'final'
                                : null
                    }
                    amountLabel={null}
                    isClient={canVerify}
                    addendumUrl={agreement.extension.open.url}
                />
            )}

            {/* The project extension still being signed or paid: both sides
                hear about it here, the student first of all. */}
            {agreement.extension.open && (
                <Panel
                    padding="lg"
                    gap="sm"
                    className="pm-reveal"
                    data-test="extension-notice"
                    style={{
                        flexDirection: 'row',
                        alignItems: 'center',
                        flexWrap: 'wrap',
                        gap: 12,
                        background:
                            'color-mix(in srgb, var(--color-accent) 9%, transparent)',
                    }}
                >
                    <ArrowsOutIcon style={{ fontSize: 20, flex: 'none' }} />
                    <span style={{ fontSize: 13.5, marginRight: 'auto' }}>
                        {extensionNotice(agreement.extension.open, canManage)}
                    </span>
                    <Btn asChild variant="primary">
                        <Link href={agreement.extension.open.url}>
                            Open addendum {agreement.extension.open.reference}
                        </Link>
                    </Btn>
                </Panel>
            )}

            <ProgressSummary agreement={agreement} />

            {/* The Gantt chart is Turnover only (owner, 2026-10-07). */}
            <PhaseTimeline
                phases={agreement.phases.filter((phase) => phase.isTurnover)}
                canEdit={canManage}
                onReschedule={reschedule}
                onEditDates={onEditDates}
            />

            {agreement.phases.map((phase, index) => (
                <PhaseChecklist
                    key={phase.id}
                    number={index + 1}
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
                                  /*
                                   * Every phase, Turnover included: the
                                   * summary's counts leave Turnover out.
                                   */
                                  unverifiedCount: agreement.phases.reduce(
                                      (count, each) =>
                                          count +
                                          each.taskCount -
                                          each.verifiedCount,
                                      0,
                                  ),
                              }
                            : null
                    }
                    extension={
                        phase.isTurnover && canComplete
                            ? agreement.extension
                            : null
                    }
                />
            ))}

            {/* Ending early takes both sides; it then completes the project. */}
            {agreement.canRequestTermination && (
                <Panel
                    padding="lg"
                    gap="sm"
                    style={{
                        flexDirection: 'row',
                        alignItems: 'center',
                        flexWrap: 'wrap',
                        gap: 12,
                    }}
                >
                    <div style={{ marginRight: 'auto', minWidth: 0 }}>
                        <div style={{ fontSize: 13.5, fontWeight: 600 }}>
                            End the collaboration early
                        </div>
                        <div style={{ fontSize: 12.5, color: MUTED(62) }}>
                            Ask the other side to terminate this agreement. It
                            ends, and the project is completed, only once they
                            agree.
                        </div>
                    </div>
                    <TerminationButton
                        url={terminationsStore.url({
                            current_team: teamSlug,
                            agreement: agreement.id,
                        })}
                        rates={canVerify}
                    />
                </Panel>
            )}
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
}: {
    side: 'student' | 'client';
    teamSlug: string;
    pendingAgreementId: number | null;
}) {
    return (
        <>
            <div
                aria-hidden="true"
                style={{ height: 1, background: 'var(--color-divider)' }}
            />
            <div
                style={{
                    minHeight: '52vh',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: 10,
                    textAlign: 'center',
                    padding: '32px 16px',
                }}
            >
                <span
                    style={{
                        width: 40,
                        height: 40,
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
                <div style={{ fontSize: 15, fontWeight: 600 }}>
                    Project Management opens when your project starts
                </div>
                <div
                    style={{
                        fontSize: 13,
                        color: MUTED(62),
                        maxWidth: 470,
                        lineHeight: 1.6,
                    }}
                >
                    {side === 'student'
                        ? 'It opens once a client accepts you and you both sign the agreement. Then you can list the tasks for each phase, plan your timeline, and mark work done for the client to check.'
                        : 'It opens once you accept a student and you both sign the agreement. The student then lists the tasks and marks work done, and you check and approve it here.'}
                </div>
                {pendingAgreementId !== null && (
                    <Btn asChild variant="primary" style={{ marginTop: 6 }}>
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
            </div>
        </>
    );
}

/**
 * The top of a running build: overall progress, the final deadline and what
 * comes next, then the phases in order with the current one picked out.
 */
function ProgressSummary({ agreement }: { agreement: ManagedAgreement }) {
    const { summary } = agreement;
    const daysLeft = summary.daysToFinalDeadline;
    const RULE = '1px solid var(--color-divider)';

    const daysLabel =
        daysLeft === null
            ? null
            : daysLeft > 1
              ? `${daysLeft} days left`
              : daysLeft === 1
                ? '1 day left'
                : daysLeft === 0
                  ? 'Due today'
                  : `${Math.abs(daysLeft)} days over`;

    return (
        <Panel
            padding="none"
            gap="none"
            className="pm-reveal"
            style={{ overflow: 'hidden' }}
        >
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                }}
            >
                <SummaryStat label="Overall">
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'baseline',
                            gap: 6,
                        }}
                    >
                        <span style={STAT_VALUE}>{summary.progress}%</span>
                        <span style={{ fontSize: 11.5, color: MUTED(55) }}>
                            overall
                        </span>
                    </div>
                    <ProgressBar
                        value={summary.progress}
                        label="Overall progress"
                    />
                    <span style={{ fontSize: 11.5, color: MUTED(62) }}>
                        <strong style={{ color: 'var(--color-text)' }}>
                            {summary.verifiedCount} of {summary.taskCount}
                        </strong>{' '}
                        tasks verified (Turnover not counted)
                    </span>
                    {summary.submittedCount > 0 && (
                        <span style={{ fontSize: 11.5, color: MUTED(62) }}>
                            {summary.submittedCount} pending client review
                        </span>
                    )}
                </SummaryStat>

                <SummaryStat label="Final deadline" divided>
                    <span style={STAT_VALUE}>
                        {summary.finalDeadline
                            ? shortDate(summary.finalDeadline)
                            : 'Not set'}
                    </span>
                    {summary.finalDeadline &&
                        !agreement.isCompleted &&
                        daysLabel && (
                            <Tag
                                variant="neutral"
                                style={{
                                    alignSelf: 'flex-start',
                                    ...(daysLeft !== null && daysLeft < 0
                                        ? { color: 'var(--destructive)' }
                                        : {}),
                                }}
                            >
                                {daysLabel}
                            </Tag>
                        )}
                    {summary.overdueTaskCount > 0 && !agreement.isCompleted && (
                        <span
                            style={{
                                fontSize: 11.5,
                                color: 'var(--destructive)',
                            }}
                        >
                            {summary.overdueTaskCount} overdue
                        </span>
                    )}
                </SummaryStat>

                <SummaryStat label="Next" divided>
                    <span style={STAT_VALUE}>
                        {summary.nextMilestone?.title ??
                            (agreement.isCompleted
                                ? 'Completed'
                                : 'Nothing due')}
                    </span>
                    {summary.nextMilestone?.dueOn && (
                        <span style={{ fontSize: 11.5, color: MUTED(58) }}>
                            due {summary.nextMilestone.dueOn}
                        </span>
                    )}
                </SummaryStat>
            </div>

            <div
                style={{
                    display: 'grid',
                    gap: 10,
                    padding: 'var(--space-3) var(--space-4) var(--space-4)',
                    borderTop: RULE,
                    background: MUTED(3),
                }}
            >
                {summary.currentPhase && (
                    <span style={{ fontSize: 12, color: MUTED(62) }}>
                        Current phase ·{' '}
                        <strong style={{ color: 'var(--color-text)' }}>
                            {summary.currentPhase.title}
                        </strong>
                    </span>
                )}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        flexWrap: 'wrap',
                        gap: 8,
                    }}
                >
                    {/*
                     * One card per phase: Objective & Scope and Turnover. Each
                     * Section VII service is one Objective & Scope task (the
                     * objective its title, the scope its description). Turnover
                     * is listed but never counted in the percentage.
                     */}
                    {agreement.phases.map((phase, index) => (
                        <Fragment key={phase.id}>
                            {index > 0 && (
                                <span
                                    aria-hidden="true"
                                    style={{
                                        width: 18,
                                        borderTop: `2px dotted ${MUTED(25)}`,
                                        flex: 'none',
                                    }}
                                />
                            )}
                            <PhaseCard
                                phase={phase}
                                number={index + 1}
                                isCurrent={
                                    summary.currentPhase?.id === phase.id
                                }
                            />
                        </Fragment>
                    ))}
                </div>
            </div>
        </Panel>
    );
}

const STAT_VALUE: React.CSSProperties = {
    fontFamily: 'var(--font-heading)',
    fontSize: 24,
    lineHeight: 1.15,
};

function SummaryStat({
    label,
    divided = false,
    children,
}: {
    label: string;
    divided?: boolean;
    children: ReactNode;
}) {
    return (
        <div
            style={{
                display: 'flex',
                flexDirection: 'column',
                gap: 6,
                padding: 'var(--space-4)',
                borderLeft: divided
                    ? '1px solid var(--color-divider)'
                    : undefined,
            }}
        >
            <span
                style={{
                    fontSize: 10.5,
                    letterSpacing: '0.06em',
                    textTransform: 'uppercase',
                    color: MUTED(55),
                }}
            >
                {label}
            </span>
            {children}
        </div>
    );
}

function ProgressBar({ value, label }: { value: number; label: string }) {
    return (
        <div
            role="progressbar"
            aria-valuenow={value}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label={label}
            style={{
                height: 4,
                borderRadius: 2,
                background: MUTED(10),
                overflow: 'hidden',
            }}
        >
            <div
                style={{
                    width: `${value}%`,
                    height: '100%',
                    background: 'var(--color-accent)',
                }}
            />
        </div>
    );
}

/** One phase in the summary: its number, state and how much of it is verified. */
function PhaseCard({
    phase,
    number,
    isCurrent,
}: {
    phase: Phase;
    number: number;
    isCurrent: boolean;
}) {
    return (
        <div
            style={{
                flex: '1 1 260px',
                minWidth: 0,
                display: 'grid',
                gap: 6,
                padding: '12px 14px',
                borderRadius: 'var(--radius-lg)',
                background: 'var(--color-surface)',
                border: isCurrent
                    ? '1.5px solid var(--color-accent)'
                    : '1px solid var(--color-divider)',
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <PhaseNumber
                    number={number}
                    filled={phase.state !== 'upcoming'}
                />
                <span style={{ fontSize: 13.5, fontWeight: 600 }}>
                    {phase.title}
                </span>
                {!phase.isTurnover && (
                    <Tag variant="accent">{phase.progress}%</Tag>
                )}
                <span style={{ marginLeft: 'auto' }}>
                    {phase.state === 'in_progress' ? (
                        <span
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 5,
                                padding: '2px 8px',
                                borderRadius: 999,
                                fontSize: 11,
                                background:
                                    'color-mix(in srgb, var(--color-accent) 12%, transparent)',
                            }}
                        >
                            <span
                                aria-hidden="true"
                                style={{
                                    width: 6,
                                    height: 6,
                                    borderRadius: '50%',
                                    background: 'var(--color-accent)',
                                }}
                            />
                            In progress
                        </span>
                    ) : (
                        <Tag variant={STATE_TAG[phase.state].variant}>
                            {STATE_TAG[phase.state].label}
                        </Tag>
                    )}
                </span>
            </div>
            {/* Objective & Scope has no dates. */}
            {(phase.startsOn || phase.endsOn) && (
                <span style={{ fontSize: 12, fontWeight: 600 }}>
                    {shortDate(phase.startsOn)} – {shortDate(phase.endsOn)}
                </span>
            )}
            {phase.isTurnover ? (
                <span style={{ fontSize: 11, color: MUTED(55) }}>
                    {phase.verifiedCount} of {phase.taskCount} tasks verified ·
                    not counted in progress
                </span>
            ) : (
                <>
                    <ProgressBar
                        value={phase.progress}
                        label={`${phase.title} verified`}
                    />
                    <span style={{ fontSize: 11, color: MUTED(55) }}>
                        {phase.verifiedCount} of {phase.taskCount} tasks
                        verified
                    </span>
                </>
            )}
        </div>
    );
}
