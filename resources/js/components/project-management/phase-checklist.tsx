import { router } from '@inertiajs/react';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    CalendarBlankIcon,
    CheckIcon,
    ClockCounterClockwiseIcon,
    FlagCheckeredIcon,
    LinkSimpleIcon,
    PencilSimpleIcon,
    PlusIcon,
    TrashIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';
import { toast } from 'sonner';

import {
    CompleteProjectDialog,
    DeadlineRequestDialog,
    DeclineDeadlineDialog,
    IN_PLACE,
    ProofFile,
    ReviewOutcomeDialog,
    ReviewTaskDialog,
    SubmitTaskDialog,
    TaskFormDialog,
} from '@/components/project-management/task-dialogs';
import type {
    DeadlineRequest,
    Phase,
    Task,
} from '@/components/project-management/types';
import { Btn } from '@/components/sdpc/btn';
import { PageNumbers, usePagination } from '@/components/sdpc/page-numbers';
import { Panel } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { earliestTurnoverEnd, shortDate } from '@/lib/calendar-days';
import { store as completeProject } from '@/routes/agreements/completion';
import {
    approve as approveDeadline,
    decline as declineDeadline,
    destroy as withdrawDeadline,
} from '@/routes/agreements/deadline-requests';
import { store as askFinalDeadline } from '@/routes/agreements/milestones/deadline-requests';
import {
    destroy as destroyTask,
    reorder as reorderTasks,
    sendBack as sendBackTask,
    store as storeTask,
    submit as submitTask,
    update as updateTask,
    verify as verifyTask,
    withdraw as withdrawTask,
} from '@/routes/agreements/tasks';
import { store as askTaskDeadline } from '@/routes/agreements/tasks/deadline-requests';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Dialog =
    | { kind: 'add' }
    | { kind: 'edit'; task: Task }
    | { kind: 'submit'; task: Task }
    | { kind: 'review'; task: Task }
    | { kind: 'outcome'; task: Task }
    | { kind: 'ask'; task: Task }
    | { kind: 'decline'; task: Task; request: DeadlineRequest }
    | { kind: 'ask-final' }
    | { kind: 'decline-final'; request: DeadlineRequest }
    | { kind: 'complete' }
    | null;

/** Objective & Scope tasks per page (owner, 2026-10-08). */
const TASKS_PER_PAGE = 4;

/** Tell the person why a move was refused, without a validation field to hang it on. */
const reportFailure = (errors: Record<string, string>) => {
    const message = Object.values(errors)[0];

    if (message) {
        toast.error(message);
    }
};

/**
 * One phase's checklist on Project Management.
 *
 * The student side (canManage) adds tasks, edits and reorders the ones not
 * handed over, and ticks a box to submit one for review with proof. Ticking
 * does not complete anything: the row reads "Pending client review" until the
 * client (canVerify) opens Verify and verifies or rejects it there; the
 * student reads the client's comment through Review Verification / Review
 * Rejection. Only Turnover tasks carry deadlines.
 *
 * A deadline, once set, moves only when the student side asks and the client
 * approves. The Turnover phase carries the final deadline and, for the client,
 * the Complete button that ends the collaboration.
 *
 * Every button here maps to a route the server authorises on its own, so a
 * hidden button is a convenience, not the permission.
 */
export function PhaseChecklist({
    phase,
    teamSlug,
    agreementId,
    canManage,
    canVerify,
    finalDeadline,
    finalDeadlineRequest,
    completion,
}: {
    phase: Phase;
    teamSlug: string;
    agreementId: number;
    canManage: boolean;
    canVerify: boolean;
    /** The end of Turnover, ISO: every task deadline falls on or before it. */
    finalDeadline: string | null;
    finalDeadlineRequest: DeadlineRequest | null;
    /** Set for the client on the Turnover phase of a build in flight. */
    completion: {
        projectTitle: string;
        unverifiedCount: number;
    } | null;
}) {
    const [dialog, setDialog] = useState<Dialog>(null);

    /*
     * Objective & Scope pages four tasks at a time so a long checklist does
     * not pile up (owner, 2026-10-08); Turnover shows its tasks on one page.
     */
    const perPage = phase.isTurnover
        ? Math.max(phase.tasks.length, 1)
        : TASKS_PER_PAGE;
    const { page, pageCount, setPage, pageItems } = usePagination(
        phase.tasks,
        perPage,
    );
    const firstIndex = (page - 1) * perPage;

    const taskArgs = (task: Task) => ({
        current_team: teamSlug,
        agreement: agreementId,
        task: task.id,
    });

    const phaseArgs = {
        current_team: teamSlug,
        agreement: agreementId,
        milestone: phase.id,
    };

    const requestArgs = (request: DeadlineRequest) => ({
        current_team: teamSlug,
        agreement: agreementId,
        deadlineRequest: request.id,
    });

    const withdraw = (task: Task) =>
        router.delete(withdrawTask.url(taskArgs(task)), {
            ...IN_PLACE,
            onError: reportFailure,
        });

    const approve = (request: DeadlineRequest) =>
        router.post(
            approveDeadline.url(requestArgs(request)),
            {},
            { ...IN_PLACE, onError: reportFailure },
        );

    const takeBack = (request: DeadlineRequest) =>
        router.delete(withdrawDeadline.url(requestArgs(request)), {
            ...IN_PLACE,
            onError: reportFailure,
        });

    const remove = (task: Task) => {
        if (!window.confirm(`Delete “${task.title}”?`)) {
            return;
        }

        router.delete(destroyTask.url(taskArgs(task)), {
            ...IN_PLACE,
            onError: reportFailure,
        });
    };

    const move = (index: number, direction: -1 | 1) => {
        const ids = phase.tasks.map((task) => task.id);
        const target = index + direction;

        [ids[index], ids[target]] = [ids[target], ids[index]];

        router
            .optimistic<{ agreement: { phases: Phase[] } | null }>((props) =>
                props.agreement === null
                    ? {}
                    : {
                          agreement: {
                              ...props.agreement,
                              phases: props.agreement.phases.map((each) =>
                                  each.id !== phase.id
                                      ? each
                                      : {
                                            ...each,
                                            tasks: ids.map(
                                                (id) =>
                                                    each.tasks.find(
                                                        (task) =>
                                                            task.id === id,
                                                    ) as Task,
                                            ),
                                        },
                              ),
                          },
                      },
            )
            .put(
                reorderTasks.url(phaseArgs),
                { task_ids: ids },
                { ...IN_PLACE, onError: reportFailure },
            );
    };

    const toggle = (task: Task) => {
        if (task.status === 'open') {
            setDialog({ kind: 'submit', task });
        } else if (task.status === 'submitted') {
            withdraw(task);
        }
    };

    return (
        <Panel padding="lg" gap="md" className="pm-reveal">
            <div
                style={{
                    display: 'flex',
                    alignItems: 'baseline',
                    gap: 12,
                    flexWrap: 'wrap',
                }}
            >
                <span
                    style={{
                        fontSize: 13,
                        fontWeight: 600,
                        letterSpacing: '0.04em',
                        textTransform: 'uppercase',
                        marginRight: 'auto',
                    }}
                >
                    {phase.title}
                </span>
                <span style={{ fontSize: 11.5, color: MUTED(55) }}>
                    {phase.verifiedCount} of {phase.taskCount} verified
                    {phase.isTurnover ? ' · not counted in progress' : ''}
                </span>
                {(phase.startsOn || phase.endsOn) && (
                    <span style={{ fontSize: 11.5, color: MUTED(55) }}>
                        {shortDate(phase.startsOn)} – {shortDate(phase.endsOn)}
                    </span>
                )}
            </div>

            {phase.isTurnover && finalDeadline && (
                <FinalDeadlineBar
                    finalDeadline={finalDeadline}
                    request={finalDeadlineRequest}
                    canManage={canManage}
                    canVerify={canVerify}
                    onAsk={() => setDialog({ kind: 'ask-final' })}
                    onApprove={approve}
                    onDecline={(request) =>
                        setDialog({ kind: 'decline-final', request })
                    }
                    onTakeBack={takeBack}
                />
            )}

            {phase.tasks.length === 0 && (
                <div
                    style={{
                        fontSize: 12.5,
                        color: MUTED(60),
                        padding: '6px 2px',
                    }}
                >
                    {canManage
                        ? 'No tasks yet. Add the scope and modules this phase covers.'
                        : 'The student has not listed this phase’s tasks yet.'}
                </div>
            )}

            <div style={{ display: 'grid', gap: 6 }}>
                {pageItems.map((task, offset) => {
                    /* Its place in the whole checklist, for reordering. */
                    const index = firstIndex + offset;

                    return (
                        <TaskRow
                            key={task.id}
                            task={task}
                            canManage={canManage}
                            canVerify={canVerify}
                            isFirst={index === 0}
                            isLast={index === phase.tasks.length - 1}
                            onToggle={() => toggle(task)}
                            onEdit={() => setDialog({ kind: 'edit', task })}
                            onDelete={() => remove(task)}
                            onMoveUp={() => move(index, -1)}
                            onMoveDown={() => move(index, 1)}
                            carriesDeadline={phase.isTurnover}
                            /* The client opens the file through Verify. */
                            showsProofFile={!(canVerify && !phase.isTurnover)}
                            onVerify={() => setDialog({ kind: 'review', task })}
                            onReviewOutcome={() =>
                                setDialog({ kind: 'outcome', task })
                            }
                            onAsk={() => setDialog({ kind: 'ask', task })}
                            onApprove={approve}
                            onDecline={(request) =>
                                setDialog({ kind: 'decline', task, request })
                            }
                            onTakeBack={takeBack}
                        />
                    );
                })}
            </div>

            <PageNumbers
                page={page}
                pageCount={pageCount}
                onChange={setPage}
                label={phase.title}
            />

            {(canManage || completion !== null) && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        flexWrap: 'wrap',
                    }}
                >
                    {canManage && (
                        <Btn
                            variant="ghost"
                            onClick={() => setDialog({ kind: 'add' })}
                        >
                            <PlusIcon />
                            Add task
                        </Btn>
                    )}
                    {/* The client's Complete, bottom right of Turnover. */}
                    {completion !== null && (
                        <Btn
                            variant="primary"
                            style={{ marginLeft: 'auto' }}
                            onClick={() => setDialog({ kind: 'complete' })}
                        >
                            <FlagCheckeredIcon />
                            Complete project
                        </Btn>
                    )}
                </div>
            )}

            {dialog?.kind === 'add' && (
                <TaskFormDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={storeTask.url(phaseArgs)}
                    method="post"
                    phase={phase}
                    task={null}
                    finalDeadline={finalDeadline}
                />
            )}
            {dialog?.kind === 'edit' && (
                <TaskFormDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={updateTask.url(taskArgs(dialog.task))}
                    method="patch"
                    phase={phase}
                    task={dialog.task}
                    finalDeadline={finalDeadline}
                />
            )}
            {dialog?.kind === 'submit' && (
                <SubmitTaskDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={submitTask.url(taskArgs(dialog.task))}
                    task={dialog.task}
                    fileRequired={!phase.isTurnover}
                />
            )}
            {dialog?.kind === 'review' && (
                <ReviewTaskDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    verifyUrl={verifyTask.url(taskArgs(dialog.task))}
                    rejectUrl={sendBackTask.url(taskArgs(dialog.task))}
                    task={dialog.task}
                />
            )}
            {dialog?.kind === 'outcome' && (
                <ReviewOutcomeDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    task={dialog.task}
                />
            )}
            {dialog?.kind === 'ask' && (
                <DeadlineRequestDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={askTaskDeadline.url(taskArgs(dialog.task))}
                    subject={`“${dialog.task.title}”`}
                    currentOn={dialog.task.dueOn}
                    max={finalDeadline}
                />
            )}
            {dialog?.kind === 'decline' && (
                <DeclineDeadlineDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={declineDeadline.url(requestArgs(dialog.request))}
                    request={dialog.request}
                    subject={`“${dialog.task.title}”`}
                />
            )}
            {dialog?.kind === 'ask-final' && (
                <DeadlineRequestDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={askFinalDeadline.url(phaseArgs)}
                    subject="the final deadline"
                    currentOn={finalDeadline}
                    /* Turnover runs at least a month from its start. */
                    min={
                        phase.startsOn
                            ? earliestTurnoverEnd(phase.startsOn)
                            : null
                    }
                />
            )}
            {dialog?.kind === 'decline-final' && (
                <DeclineDeadlineDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={declineDeadline.url(requestArgs(dialog.request))}
                    request={dialog.request}
                    subject="the final deadline"
                />
            )}
            {dialog?.kind === 'complete' && completion !== null && (
                <CompleteProjectDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={completeProject.url({
                        current_team: teamSlug,
                        agreement: agreementId,
                    })}
                    projectTitle={completion.projectTitle}
                    unverifiedCount={completion.unverifiedCount}
                />
            )}
        </Panel>
    );
}

/**
 * The final deadline, on the Turnover phase: the day the project is due to
 * end, and the ask to move it when there is one.
 */
function FinalDeadlineBar({
    finalDeadline,
    request,
    canManage,
    canVerify,
    onAsk,
    onApprove,
    onDecline,
    onTakeBack,
}: {
    finalDeadline: string;
    request: DeadlineRequest | null;
    canManage: boolean;
    canVerify: boolean;
    onAsk: () => void;
    onApprove: (request: DeadlineRequest) => void;
    onDecline: (request: DeadlineRequest) => void;
    onTakeBack: (request: DeadlineRequest) => void;
}) {
    const pending = request?.status === 'pending' ? request : null;

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                flexWrap: 'wrap',
                padding: '10px 12px',
                borderRadius: 'var(--radius-md)',
                background:
                    'color-mix(in srgb, var(--color-accent) 9%, transparent)',
                fontSize: 12.5,
            }}
        >
            <FlagCheckeredIcon style={{ flex: 'none' }} />
            <span style={{ marginRight: 'auto' }}>
                Final deadline{' '}
                <strong style={{ color: 'var(--color-text)' }}>
                    {shortDate(finalDeadline)}
                </strong>
                <span style={{ color: MUTED(58) }}>
                    {' '}
                    · the project ends here
                </span>
            </span>
            <DeadlineAsk
                request={request}
                canManage={canManage}
                canVerify={canVerify}
                onApprove={onApprove}
                onDecline={onDecline}
                onTakeBack={onTakeBack}
            />
            {canManage && pending === null && (
                <Btn variant="ghost" onClick={onAsk}>
                    <ClockCounterClockwiseIcon />
                    Ask to move it
                </Btn>
            )}
        </div>
    );
}

/**
 * Where an ask to move a deadline stands, and the moves open to the viewer:
 * the client approves or declines a waiting one, the student side may take it
 * back. The last answer stays visible so a declined ask is not forgotten.
 */
function DeadlineAsk({
    request,
    canManage,
    canVerify,
    onApprove,
    onDecline,
    onTakeBack,
}: {
    request: DeadlineRequest | null;
    canManage: boolean;
    canVerify: boolean;
    onApprove: (request: DeadlineRequest) => void;
    onDecline: (request: DeadlineRequest) => void;
    onTakeBack: (request: DeadlineRequest) => void;
}) {
    if (request === null) {
        return null;
    }

    if (request.status === 'pending') {
        return (
            <span
                style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: 6,
                    flexWrap: 'wrap',
                }}
            >
                <Tag
                    variant="accent-2"
                    title={request.reason ?? undefined}
                    className="pm-pulse"
                >
                    New date asked: {shortDate(request.proposedOn)}
                </Tag>
                {canVerify && (
                    <>
                        <Btn
                            variant="primary"
                            onClick={() => onApprove(request)}
                        >
                            <CheckIcon />
                            Approve
                        </Btn>
                        <Btn variant="ghost" onClick={() => onDecline(request)}>
                            Decline
                        </Btn>
                    </>
                )}
                {canManage && (
                    <Btn variant="ghost" onClick={() => onTakeBack(request)}>
                        Take back
                    </Btn>
                )}
            </span>
        );
    }

    return (
        <span
            style={{ fontSize: 11.5, color: MUTED(60) }}
            title={request.decisionNote ?? undefined}
        >
            {request.status === 'approved'
                ? `Moved from ${request.previousOn ? shortDate(request.previousOn) : 'no date'}`
                : `Client kept ${request.previousOn ? shortDate(request.previousOn) : 'the date'}`}
            {request.decisionNote ? ` · “${request.decisionNote}”` : ''}
        </span>
    );
}

function TaskRow({
    task,
    canManage,
    canVerify,
    isFirst,
    isLast,
    carriesDeadline,
    showsProofFile,
    onToggle,
    onEdit,
    onDelete,
    onMoveUp,
    onMoveDown,
    onVerify,
    onReviewOutcome,
    onAsk,
    onApprove,
    onDecline,
    onTakeBack,
}: {
    task: Task;
    canManage: boolean;
    canVerify: boolean;
    isFirst: boolean;
    isLast: boolean;
    /** Only Turnover tasks carry deadlines; Objective & Scope has none. */
    carriesDeadline: boolean;
    /** False for the client in Objective & Scope: the file is seen through Verify. */
    showsProofFile: boolean;
    onToggle: () => void;
    onEdit: () => void;
    onDelete: () => void;
    onMoveUp: () => void;
    onMoveDown: () => void;
    /** Opens the review dialog: the only way to Verify or Reject. */
    onVerify: () => void;
    /** The student's Review Verification / Review Rejection. */
    onReviewOutcome: () => void;
    onAsk: () => void;
    onApprove: (request: DeadlineRequest) => void;
    onDecline: (request: DeadlineRequest) => void;
    onTakeBack: (request: DeadlineRequest) => void;
}) {
    const checked = task.status !== 'open';
    /* The student ticks open work and unticks work not yet reviewed; verified is final. */
    const canToggle = canManage && task.status !== 'verified';
    const hasProof = task.proofNote || task.proofUrl || task.proofHref;
    const canAsk =
        canManage &&
        task.status !== 'verified' &&
        task.dueOn !== null &&
        task.deadlineRequest?.status !== 'pending';

    return (
        <div className="pm-task" data-status={task.status}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 12,
                    flexWrap: 'wrap',
                }}
            >
                <button
                    type="button"
                    role="checkbox"
                    aria-checked={checked}
                    aria-label={
                        task.status === 'open'
                            ? `Submit ${task.title} for review`
                            : task.status === 'submitted'
                              ? `Withdraw ${task.title} from review`
                              : `${task.title} is verified`
                    }
                    disabled={!canToggle}
                    onClick={onToggle}
                    className="pm-check"
                    style={{
                        width: 18,
                        height: 18,
                        flex: 'none',
                        borderRadius: 4,
                        border: `1.5px solid ${checked ? 'var(--color-accent)' : MUTED(45)}`,
                        background:
                            task.status === 'verified'
                                ? 'var(--color-accent)'
                                : 'transparent',
                        color:
                            task.status === 'verified'
                                ? 'var(--color-bg)'
                                : 'var(--color-accent)',
                        display: 'grid',
                        placeItems: 'center',
                        padding: 0,
                        cursor: canToggle ? 'pointer' : 'default',
                    }}
                >
                    {checked && <CheckIcon weight="bold" size={12} />}
                </button>

                <div style={{ flex: '1 1 200px', minWidth: 0 }}>
                    <div
                        style={{
                            fontSize: 13,
                            color:
                                task.status === 'verified'
                                    ? MUTED(70)
                                    : 'var(--color-text)',
                        }}
                    >
                        {task.title}
                    </div>
                    {task.description && (
                        <div style={{ fontSize: 11.5, color: MUTED(58) }}>
                            {task.description}
                        </div>
                    )}
                </div>

                {(carriesDeadline || task.dueOn !== null) && (
                    <DueDate task={task} />
                )}

                <StatusLine
                    task={task}
                    canManage={canManage}
                    canVerify={canVerify}
                />

                {canVerify && task.status === 'submitted' && (
                    <Btn variant="primary" onClick={onVerify}>
                        <CheckIcon />
                        Verify
                    </Btn>
                )}

                {/* The client's answer, for the student side to read. */}
                {canManage &&
                    (task.status === 'verified' ||
                        (task.status === 'open' && task.reviewNote)) && (
                        <Btn variant="ghost" onClick={onReviewOutcome}>
                            {task.status === 'verified'
                                ? 'Review Verification'
                                : 'Review Rejection'}
                        </Btn>
                    )}

                {(canAsk || (canManage && task.status === 'open')) && (
                    <div style={{ display: 'flex', gap: 2 }}>
                        {canAsk && (
                            <Btn
                                icon
                                variant="ghost"
                                aria-label={`Ask for a new date for ${task.title}`}
                                title="Ask for a new date"
                                onClick={onAsk}
                                style={{ width: 28, height: 28 }}
                            >
                                <ClockCounterClockwiseIcon />
                            </Btn>
                        )}
                        {canManage && task.status === 'open' && (
                            <>
                                <Btn
                                    icon
                                    variant="ghost"
                                    aria-label="Move up"
                                    disabled={isFirst}
                                    onClick={onMoveUp}
                                    style={{ width: 28, height: 28 }}
                                >
                                    <ArrowUpIcon />
                                </Btn>
                                <Btn
                                    icon
                                    variant="ghost"
                                    aria-label="Move down"
                                    disabled={isLast}
                                    onClick={onMoveDown}
                                    style={{ width: 28, height: 28 }}
                                >
                                    <ArrowDownIcon />
                                </Btn>
                                <Btn
                                    icon
                                    variant="ghost"
                                    aria-label={`Edit ${task.title}`}
                                    onClick={onEdit}
                                    style={{ width: 28, height: 28 }}
                                >
                                    <PencilSimpleIcon />
                                </Btn>
                                <Btn
                                    icon
                                    variant="ghost"
                                    aria-label={`Delete ${task.title}`}
                                    onClick={onDelete}
                                    style={{ width: 28, height: 28 }}
                                >
                                    <TrashIcon />
                                </Btn>
                            </>
                        )}
                    </div>
                )}
            </div>

            {task.deadlineRequest && (
                <div style={{ paddingLeft: 30 }}>
                    <DeadlineAsk
                        request={task.deadlineRequest}
                        canManage={canManage}
                        canVerify={canVerify}
                        onApprove={onApprove}
                        onDecline={onDecline}
                        onTakeBack={onTakeBack}
                    />
                </div>
            )}

            {task.status !== 'open' && hasProof && (
                <div
                    style={{
                        display: 'flex',
                        gap: 12,
                        flexWrap: 'wrap',
                        alignItems: 'center',
                        paddingLeft: 30,
                        fontSize: 11.5,
                        color: MUTED(70),
                    }}
                >
                    {task.proofNote && (
                        <span style={{ flex: '1 1 100%' }}>
                            {task.proofNote}
                        </span>
                    )}
                    {task.proofUrl && (
                        <a
                            href={task.proofUrl}
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            data-inline-link=""
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 4,
                            }}
                        >
                            <LinkSimpleIcon />
                            Open link
                        </a>
                    )}
                    {showsProofFile && task.proofHref && (
                        <ProofFile
                            href={task.proofHref}
                            name={task.proofName}
                        />
                    )}
                    {task.submittedAt && (
                        <span>Submitted {task.submittedAt}</span>
                    )}
                </div>
            )}
        </div>
    );
}

/** The task's deadline, marked when it has passed with the work not verified. */
function DueDate({ task }: { task: Task }) {
    if (task.dueOn === null) {
        return task.status === 'verified' ? null : (
            <span style={{ fontSize: 11.5, color: MUTED(45) }}>
                No deadline
            </span>
        );
    }

    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 4,
                fontSize: 11.5,
                color: task.isOverdue ? 'var(--destructive)' : MUTED(62),
                fontWeight: task.isOverdue ? 600 : undefined,
            }}
        >
            <CalendarBlankIcon />
            {task.isOverdue ? 'Overdue · ' : 'Due '}
            {shortDate(task.dueOn)}
        </span>
    );
}

function StatusLine({
    task,
    canManage,
    canVerify,
}: {
    task: Task;
    canManage: boolean;
    canVerify: boolean;
}) {
    if (task.status === 'verified') {
        return (
            <Tag
                variant="accent"
                title={
                    task.verifiedAt ? `Verified ${task.verifiedAt}` : undefined
                }
            >
                Verified{task.verifiedBy ? ` by ${task.verifiedBy}` : ''}
            </Tag>
        );
    }

    if (task.status === 'submitted') {
        return <Tag variant="outline">Pending client review</Tag>;
    }

    return (
        <span style={{ fontSize: 11.5, color: MUTED(55) }}>
            {canManage
                ? 'Check to submit for review'
                : canVerify
                  ? 'Waiting on the student'
                  : 'To do'}
        </span>
    );
}
