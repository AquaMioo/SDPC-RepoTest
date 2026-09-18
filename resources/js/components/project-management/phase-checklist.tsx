import { router } from '@inertiajs/react';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    CheckIcon,
    LinkSimpleIcon,
    PaperclipIcon,
    PencilSimpleIcon,
    PlusIcon,
    TrashIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';
import { toast } from 'sonner';

import {
    IN_PLACE,
    ReturnTaskDialog,
    SubmitTaskDialog,
    TaskFormDialog,
} from '@/components/project-management/task-dialogs';
import type { Phase, Task } from '@/components/project-management/types';
import { Btn } from '@/components/sdpc/btn';
import { Panel } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { shortDate } from '@/lib/calendar-days';
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

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Dialog =
    | { kind: 'add' }
    | { kind: 'edit'; task: Task }
    | { kind: 'submit'; task: Task }
    | { kind: 'return'; task: Task }
    | null;

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
 * The student (canManage) adds tasks, edits and reorders the ones not handed
 * over, and ticks a box to submit one for review with proof. Ticking does not
 * complete anything: the row reads "Pending client review" until the client
 * (canVerify) verifies it or sends it back with a note.
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
}: {
    phase: Phase;
    teamSlug: string;
    agreementId: number;
    canManage: boolean;
    canVerify: boolean;
}) {
    const [dialog, setDialog] = useState<Dialog>(null);

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

    const withdraw = (task: Task) =>
        router.delete(withdrawTask.url(taskArgs(task)), {
            ...IN_PLACE,
            onError: reportFailure,
        });

    const verify = (task: Task) =>
        router.post(
            verifyTask.url(taskArgs(task)),
            {},
            { ...IN_PLACE, onError: reportFailure },
        );

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
        <Panel padding="lg" gap="md">
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
                </span>
                <span style={{ fontSize: 11.5, color: MUTED(55) }}>
                    {shortDate(phase.startsOn)} – {shortDate(phase.endsOn)}
                </span>
            </div>

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
                {phase.tasks.map((task, index) => (
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
                        onVerify={() => verify(task)}
                        onReturn={() => setDialog({ kind: 'return', task })}
                    />
                ))}
            </div>

            {canManage && (
                <Btn
                    variant="ghost"
                    style={{ alignSelf: 'flex-start' }}
                    onClick={() => setDialog({ kind: 'add' })}
                >
                    <PlusIcon />
                    Add task
                </Btn>
            )}

            {dialog?.kind === 'add' && (
                <TaskFormDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={storeTask.url(phaseArgs)}
                    method="post"
                    phase={phase}
                    task={null}
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
                />
            )}
            {dialog?.kind === 'submit' && (
                <SubmitTaskDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={submitTask.url(taskArgs(dialog.task))}
                    task={dialog.task}
                />
            )}
            {dialog?.kind === 'return' && (
                <ReturnTaskDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={sendBackTask.url(taskArgs(dialog.task))}
                    task={dialog.task}
                />
            )}
        </Panel>
    );
}

function TaskRow({
    task,
    canManage,
    canVerify,
    isFirst,
    isLast,
    onToggle,
    onEdit,
    onDelete,
    onMoveUp,
    onMoveDown,
    onVerify,
    onReturn,
}: {
    task: Task;
    canManage: boolean;
    canVerify: boolean;
    isFirst: boolean;
    isLast: boolean;
    onToggle: () => void;
    onEdit: () => void;
    onDelete: () => void;
    onMoveUp: () => void;
    onMoveDown: () => void;
    onVerify: () => void;
    onReturn: () => void;
}) {
    const checked = task.status !== 'open';
    /* The student ticks open work and unticks work not yet reviewed; verified is final. */
    const canToggle = canManage && task.status !== 'verified';
    const hasProof = task.proofNote || task.proofUrl || task.proofHref;

    return (
        <div
            style={{
                borderRadius: 'var(--radius-md)',
                background: MUTED(4),
                padding: '10px 12px',
                display: 'grid',
                gap: 6,
            }}
        >
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

                <StatusLine
                    task={task}
                    canManage={canManage}
                    canVerify={canVerify}
                />

                {canVerify && task.status === 'submitted' && (
                    <div style={{ display: 'flex', gap: 6 }}>
                        <Btn variant="primary" onClick={onVerify}>
                            <CheckIcon />
                            Verify
                        </Btn>
                        <Btn variant="ghost" onClick={onReturn}>
                            Send back
                        </Btn>
                    </div>
                )}

                {canManage && task.status === 'open' && (
                    <div style={{ display: 'flex', gap: 2 }}>
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
                    </div>
                )}
            </div>

            {task.status === 'open' && task.reviewNote && (
                <div
                    style={{
                        fontSize: 11.5,
                        paddingLeft: 30,
                        color: MUTED(75),
                    }}
                >
                    <strong>Sent back:</strong> {task.reviewNote}
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
                    {/* A plain anchor: the proof route streams a file, it is not an Inertia page. */}
                    {task.proofHref && (
                        <a
                            href={task.proofHref}
                            target="_blank"
                            rel="noopener noreferrer"
                            data-inline-link=""
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 4,
                            }}
                        >
                            <PaperclipIcon />
                            {task.proofName ?? 'Attachment'}
                        </a>
                    )}
                    {task.submittedAt && (
                        <span>Submitted {task.submittedAt}</span>
                    )}
                </div>
            )}
        </div>
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
