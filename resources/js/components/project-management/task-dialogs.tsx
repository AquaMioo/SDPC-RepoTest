import { router } from '@inertiajs/react';
import {
    CheckIcon,
    FilePdfIcon,
    LinkSimpleIcon,
    PaperclipIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import InputError from '@/components/input-error';
import type {
    DeadlineRequest,
    Phase,
    Task,
} from '@/components/project-management/types';
import { Btn } from '@/components/sdpc/btn';
import { Input, Textarea } from '@/components/sdpc/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    earliestTurnoverEnd,
    latestTurnoverStart,
    shortDate,
    timelineWindow,
} from '@/lib/calendar-days';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * How every request from this screen goes out: re-read the agreement only and
 * keep the rest of the page — open dialogs, scroll, the timeline — in place.
 * Without it a router visit rebuilds the whole page, which is the "reload"
 * QA reported on messaging reactions.
 */
export const IN_PLACE = {
    preserveScroll: true,
    preserveState: true,
    only: ['agreement', 'agreements'],
};

type Errors = Record<string, string>;

/** The earlier of two Y-m-d dates, ignoring a missing one. */
function earlierOf(first: string | null | undefined, second: string): string {
    return first && first < second ? first : second;
}

/** The later of two Y-m-d dates, ignoring a missing one. */
function laterOf(first: string | null | undefined, second: string): string {
    return first && first > second ? first : second;
}

function Shell({
    open,
    onOpenChange,
    title,
    description,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                {children}
            </DialogContent>
        </Dialog>
    );
}

function Footer({
    busy,
    label,
    onSubmit,
    onCancel,
}: {
    busy: boolean;
    label: string;
    onSubmit: () => void;
    onCancel: () => void;
}) {
    /* A direct child of DialogContent, so gap-3 only — see .ai/rules/components.md. */
    return (
        <DialogFooter className="gap-3">
            <Btn variant="primary" disabled={busy} onClick={onSubmit}>
                {busy && <Spinner />}
                {label}
            </Btn>
            <Btn variant="ghost" disabled={busy} onClick={onCancel}>
                Cancel
            </Btn>
        </DialogFooter>
    );
}

/**
 * Add a task to a phase, or rename one that has not been submitted.
 *
 * Only a Turnover task may carry a deadline, on or before the final deadline;
 * Objective & Scope has none (owner, 2026-10-07). A deadline is set freely
 * once; after that it moves only with the client's approval, so the edit
 * dialog shows it but will not change it.
 */
export function TaskFormDialog({
    open,
    onOpenChange,
    url,
    method,
    phase,
    task,
    finalDeadline,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    method: 'post' | 'patch';
    phase: Phase;
    task: Task | null;
    /** The end of Turnover, ISO: no task may be due after it. */
    finalDeadline: string | null;
}) {
    const [title, setTitle] = useState(task?.title ?? '');
    const [description, setDescription] = useState(task?.description ?? '');
    const [dueOn, setDueOn] = useState(task?.dueOn ?? '');
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    /* Locked once set: moving it is an ask to the client, not an edit. */
    const deadlineLocked = task !== null && task.dueOn !== null;
    const carriesDeadline = phase.isTurnover;

    const save = () => {
        setBusy(true);

        router[method](
            url,
            {
                title,
                description,
                ...(deadlineLocked || !carriesDeadline
                    ? {}
                    : { due_on: dueOn || null }),
            },
            {
                ...IN_PLACE,
                onSuccess: () => onOpenChange(false),
                onError: setErrors,
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Shell
            open={open}
            onOpenChange={onOpenChange}
            title={task === null ? `Add a task to ${phase.title}` : 'Edit task'}
            description="Name a piece of scope or a module the client can check against."
        >
            <div style={{ display: 'grid', gap: 12 }}>
                <div className="field">
                    <label htmlFor="task-title">Task</label>
                    <Input
                        id="task-title"
                        value={title}
                        /* A Scope task carries a whole Section VII scope. */
                        maxLength={2000}
                        autoFocus
                        placeholder="e.g. Authentication & roles"
                        onChange={(event) => setTitle(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                save();
                            }
                        }}
                        aria-invalid={Boolean(errors.title)}
                    />
                    <InputError
                        message={errors.title ?? errors.task}
                        className="mt-1 text-[11px]"
                    />
                </div>
                <div className="field">
                    <label htmlFor="task-description">Details (optional)</label>
                    <Textarea
                        id="task-description"
                        value={description}
                        maxLength={2000}
                        onChange={(event) => setDescription(event.target.value)}
                    />
                    <InputError
                        message={errors.description}
                        className="mt-1 text-[11px]"
                    />
                </div>
                {carriesDeadline && (
                    <div className="field">
                        <label htmlFor="task-due">Deadline (optional)</label>
                        <Input
                            id="task-due"
                            type="date"
                            value={dueOn}
                            min={timelineWindow().min}
                            max={earlierOf(finalDeadline, timelineWindow().max)}
                            disabled={deadlineLocked}
                            onChange={(event) => setDueOn(event.target.value)}
                            aria-invalid={Boolean(errors.due_on)}
                        />
                        <span
                            style={{
                                fontSize: 11,
                                color: MUTED(55),
                                marginTop: 4,
                            }}
                        >
                            {deadlineLocked
                                ? 'A set deadline moves only with the client’s approval. Use “Ask for a new date” on the task.'
                                : finalDeadline
                                  ? `On or before the final deadline, ${shortDate(finalDeadline)}.`
                                  : 'When this task is due.'}
                        </span>
                        <InputError
                            message={errors.due_on}
                            className="mt-1 text-[11px]"
                        />
                    </div>
                )}
            </div>
            <Footer
                busy={busy}
                label={task === null ? 'Add task' : 'Save'}
                onSubmit={save}
                onCancel={() => onOpenChange(false)}
            />
        </Shell>
    );
}

/**
 * Check a task off: hand it to the client with proof.
 *
 * A note, a link and a file are each optional, but at least one is needed —
 * the server says so if none is given. A file from an earlier submission is
 * kept unless the student removes it.
 */
export function SubmitTaskDialog({
    open,
    onOpenChange,
    url,
    task,
    fileRequired,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    task: Task;
    /** Objective & Scope tasks need a file as proof; Turnover's do not. */
    fileRequired: boolean;
}) {
    const [note, setNote] = useState(task.proofNote ?? '');
    const [link, setLink] = useState(task.proofUrl ?? '');
    const [file, setFile] = useState<File | null>(null);
    const [removeFile, setRemoveFile] = useState(false);
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    const submit = () => {
        setBusy(true);

        router.post(
            url,
            {
                proof_note: note,
                proof_url: link,
                remove_file: removeFile ? 1 : 0,
                ...(file ? { proof_file: file } : {}),
            },
            {
                ...IN_PLACE,
                forceFormData: true,
                onSuccess: () => onOpenChange(false),
                onError: setErrors,
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Shell
            open={open}
            onOpenChange={onOpenChange}
            title={`Submit “${task.title}” for review`}
            description="This does not complete the task. It stays pending until the client reviews your proof and verifies it."
        >
            <div style={{ display: 'grid', gap: 12 }}>
                {task.reviewNote && (
                    <div
                        style={{
                            fontSize: 12,
                            padding: '8px 10px',
                            borderRadius: 'var(--radius-md)',
                            background:
                                'color-mix(in srgb, var(--color-accent) 10%, transparent)',
                        }}
                    >
                        <strong>Client’s note:</strong> {task.reviewNote}
                    </div>
                )}

                <div className="field">
                    <label htmlFor="proof-note">What was done</label>
                    <Textarea
                        id="proof-note"
                        value={note}
                        maxLength={2000}
                        placeholder="e.g. Wireframes walked through in Monday’s consultation."
                        onChange={(event) => setNote(event.target.value)}
                        aria-invalid={Boolean(errors.proof_note)}
                    />
                    <InputError
                        message={errors.proof_note ?? errors.task}
                        className="mt-1 text-[11px]"
                    />
                </div>

                <div className="field">
                    <label htmlFor="proof-url">Link (optional)</label>
                    <Input
                        id="proof-url"
                        type="url"
                        value={link}
                        placeholder="https://github.com/… or a Figma, Drive or deployed link"
                        onChange={(event) => setLink(event.target.value)}
                        aria-invalid={Boolean(errors.proof_url)}
                    />
                    <InputError
                        message={errors.proof_url}
                        className="mt-1 text-[11px]"
                    />
                </div>

                <div className="field">
                    <label htmlFor="proof-file">
                        {fileRequired ? 'File' : 'File (optional)'}
                    </label>
                    {task.proofName && !file && (
                        <label
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                fontSize: 12,
                                marginBottom: 6,
                                color: MUTED(75),
                            }}
                        >
                            <PaperclipIcon />
                            <span
                                style={{
                                    flex: 1,
                                    textDecoration: removeFile
                                        ? 'line-through'
                                        : undefined,
                                }}
                            >
                                {task.proofName}
                            </span>
                            <input
                                type="checkbox"
                                checked={removeFile}
                                onChange={(event) =>
                                    setRemoveFile(event.target.checked)
                                }
                            />
                            Remove
                        </label>
                    )}
                    <Input
                        id="proof-file"
                        type="file"
                        /* A file kept from the last submission still counts. */
                        required={
                            fileRequired && !(task.proofName && !removeFile)
                        }
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        onChange={(event) =>
                            setFile(event.target.files?.[0] ?? null)
                        }
                        aria-invalid={Boolean(errors.proof_file)}
                    />
                    <span
                        style={{ fontSize: 11, color: MUTED(55), marginTop: 4 }}
                    >
                        JPG, PNG, WebP or PDF, up to 10 MB. Only you and the
                        client can open it.
                    </span>
                    <InputError
                        message={errors.proof_file}
                        className="mt-1 text-[11px]"
                    />
                </div>
            </div>
            <Footer
                busy={busy}
                label="Submit for review"
                onSubmit={submit}
                onCancel={() => onOpenChange(false)}
            />
        </Shell>
    );
}

/**
 * The client's Verify: the student's submission, the client's comment, and
 * the two answers (owner, 2026-10-07).
 *
 * The only way to Reject. Rejecting sends the task back to the student as not
 * done and needs the comment, so they know what to fix; verifying keeps an
 * optional one. Either way the student reads it through Review Verification /
 * Review Rejection.
 */
export function ReviewTaskDialog({
    open,
    onOpenChange,
    verifyUrl,
    rejectUrl,
    task,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    verifyUrl: string;
    rejectUrl: string;
    task: Task;
}) {
    const [comment, setComment] = useState('');
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    const answer = (url: string) => {
        setBusy(true);

        router.post(
            url,
            { review_note: comment },
            {
                ...IN_PLACE,
                onSuccess: () => onOpenChange(false),
                onError: setErrors,
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Shell
            open={open}
            onOpenChange={onOpenChange}
            title={`Review “${task.title}”`}
            description="What the student sent. Verify it, or reject it to send it back."
        >
            <div
                data-test="review-task-dialog"
                style={{ display: 'grid', gap: 12, minWidth: 0 }}
            >
                {task.description && (
                    <ReviewBlock label="Description">
                        {task.description}
                    </ReviewBlock>
                )}

                <ReviewBlock label="What was sent">
                    {task.proofNote ?? (
                        <span style={{ color: MUTED(55) }}>No note.</span>
                    )}
                </ReviewBlock>

                {(task.proofUrl || task.proofHref) && (
                    <div
                        style={{
                            display: 'flex',
                            gap: 12,
                            flexWrap: 'wrap',
                            alignItems: 'center',
                            fontSize: 12,
                        }}
                    >
                        {task.proofHref && (
                            <ProofFile
                                href={task.proofHref}
                                name={task.proofName}
                            />
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
                    </div>
                )}

                {task.submittedAt && (
                    <span style={{ fontSize: 11, color: MUTED(55) }}>
                        Submitted {task.submittedAt}
                    </span>
                )}

                <div className="field">
                    <label htmlFor="review-comment">
                        Comment (required to reject)
                    </label>
                    <Textarea
                        id="review-comment"
                        value={comment}
                        maxLength={2000}
                        onChange={(event) => setComment(event.target.value)}
                        aria-invalid={Boolean(errors.review_note)}
                    />
                    <InputError
                        message={errors.review_note ?? errors.task}
                        className="mt-1 text-[11px]"
                    />
                </div>
            </div>
            {/* A direct child of DialogContent, so gap-3 only — see .ai/rules/components.md. */}
            <DialogFooter className="gap-3">
                <Btn
                    variant="primary"
                    disabled={busy}
                    onClick={() => answer(verifyUrl)}
                >
                    {busy && <Spinner />}
                    <CheckIcon />
                    Verify
                </Btn>
                <Btn
                    variant="ghost"
                    disabled={busy}
                    style={{ color: 'var(--destructive)' }}
                    onClick={() => answer(rejectUrl)}
                >
                    Reject
                </Btn>
                <Btn
                    variant="ghost"
                    disabled={busy}
                    onClick={() => onOpenChange(false)}
                >
                    Cancel
                </Btn>
            </DialogFooter>
        </Shell>
    );
}

/**
 * The student's Review Verification / Review Rejection: what the client said
 * when they verified or rejected the work.
 */
export function ReviewOutcomeDialog({
    open,
    onOpenChange,
    task,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    task: Task;
}) {
    const verified = task.status === 'verified';

    return (
        <Shell
            open={open}
            onOpenChange={onOpenChange}
            title={verified ? 'Review Verification' : 'Review Rejection'}
            description={
                verified
                    ? `The client verified “${task.title}”.`
                    : `The client rejected “${task.title}” and sent it back. Fix it and submit it again.`
            }
        >
            <div data-test="review-outcome-dialog">
                <ReviewBlock label="Client’s comment">
                    {task.reviewNote ?? (
                        <span style={{ color: MUTED(55) }}>
                            The client left no comment.
                        </span>
                    )}
                </ReviewBlock>
            </div>
            <DialogFooter className="gap-3">
                <Btn variant="ghost" onClick={() => onOpenChange(false)}>
                    Close
                </Btn>
            </DialogFooter>
        </Shell>
    );
}

/** A labelled block of text in the review dialogs. */
function ReviewBlock({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div style={{ display: 'grid', gap: 4, minWidth: 0 }}>
            <span style={{ fontSize: 11, color: MUTED(55) }}>{label}</span>
            <div
                style={{
                    fontSize: 12.5,
                    lineHeight: 1.55,
                    whiteSpace: 'pre-wrap',
                    padding: '8px 10px',
                    borderRadius: 'var(--radius-md)',
                    background: MUTED(5),
                }}
            >
                {children}
            </div>
        </div>
    );
}

/**
 * Exact dates for a phase — the typed alternative to dragging on the timeline.
 */
export function ScheduleDialog({
    open,
    onOpenChange,
    url,
    phase,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    phase: Phase;
}) {
    const [startsOn, setStartsOn] = useState(phase.startsOn ?? '');
    const [endsOn, setEndsOn] = useState(phase.endsOn ?? '');
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    /* Turnover's end is the final deadline: only an approved ask moves it. */
    const endLocked = phase.isTurnover && phase.endsOn !== null;
    const dateWindow = timelineWindow();

    const save = () => {
        setBusy(true);

        router.patch(
            url,
            { starts_on: startsOn, ends_on: endLocked ? phase.endsOn : endsOn },
            {
                ...IN_PLACE,
                onSuccess: () => onOpenChange(false),
                onError: setErrors,
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Shell
            open={open}
            onOpenChange={onOpenChange}
            title={`Plan ${phase.title}`}
            description={
                phase.agreedStartsOn && phase.agreedEndsOn
                    ? `The signed agreement has ${phase.agreedStartsOn} to ${phase.agreedEndsOn}. Your plan does not change the contract.`
                    : 'Your plan does not change the signed agreement.'
            }
        >
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))',
                    gap: 12,
                }}
            >
                <div className="field">
                    <label htmlFor="phase-starts">Starts</label>
                    <Input
                        id="phase-starts"
                        type="date"
                        value={startsOn}
                        /*
                         * A date that moves stays between today and a year
                         * out; Turnover also keeps a month before its end.
                         */
                        min={
                            startsOn === phase.startsOn
                                ? undefined
                                : dateWindow.min
                        }
                        max={
                            phase.isTurnover && phase.endsOn
                                ? earlierOf(
                                      latestTurnoverStart(phase.endsOn),
                                      dateWindow.max,
                                  )
                                : dateWindow.max
                        }
                        onChange={(event) => setStartsOn(event.target.value)}
                        aria-invalid={Boolean(errors.starts_on)}
                    />
                    <InputError
                        message={errors.starts_on}
                        className="mt-1 text-[11px]"
                    />
                </div>
                <div className="field">
                    <label htmlFor="phase-ends">
                        {phase.isTurnover ? 'Final deadline' : 'Ends'}
                    </label>
                    <Input
                        id="phase-ends"
                        type="date"
                        value={endLocked ? (phase.endsOn ?? '') : endsOn}
                        min={
                            phase.isTurnover && startsOn
                                ? laterOf(
                                      earliestTurnoverEnd(startsOn),
                                      dateWindow.min,
                                  )
                                : laterOf(startsOn, dateWindow.min)
                        }
                        max={dateWindow.max}
                        disabled={endLocked}
                        onChange={(event) => setEndsOn(event.target.value)}
                        aria-invalid={Boolean(errors.ends_on)}
                    />
                    {endLocked && (
                        <span
                            style={{
                                fontSize: 11,
                                color: MUTED(55),
                                marginTop: 4,
                            }}
                        >
                            Moves only with the client’s approval.
                        </span>
                    )}
                    <InputError
                        message={errors.ends_on}
                        className="mt-1 text-[11px]"
                    />
                </div>
            </div>
            <Footer
                busy={busy}
                label="Save dates"
                onSubmit={save}
                onCancel={() => onOpenChange(false)}
            />
        </Shell>
    );
}

/**
 * The student side asking the client to move a deadline — a task's, or the
 * final one. Nothing moves until the client approves.
 */
export function DeadlineRequestDialog({
    open,
    onOpenChange,
    url,
    subject,
    currentOn,
    max,
    min,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    /** What would move, e.g. “Wireframes” or “the final deadline”. */
    subject: string;
    currentOn: string | null;
    /** ISO bounds the server will hold the date to. */
    max?: string | null;
    min?: string | null;
}) {
    const [proposedOn, setProposedOn] = useState(currentOn ?? '');
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    const send = () => {
        setBusy(true);

        router.post(
            url,
            { proposed_on: proposedOn, reason },
            {
                ...IN_PLACE,
                onSuccess: () => onOpenChange(false),
                onError: setErrors,
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Shell
            open={open}
            onOpenChange={onOpenChange}
            title={`Ask for a new date for ${subject}`}
            description={`Currently ${currentOn ? shortDate(currentOn) : 'not set'}. The client approves or declines it; until then the current date stands.`}
        >
            <div style={{ display: 'grid', gap: 12 }}>
                <div className="field">
                    <label htmlFor="proposed-on">New date</label>
                    <Input
                        id="proposed-on"
                        type="date"
                        value={proposedOn}
                        /* Whatever else holds it, a date asked for is today to a year out. */
                        min={laterOf(min, timelineWindow().min)}
                        max={earlierOf(max, timelineWindow().max)}
                        autoFocus
                        onChange={(event) => setProposedOn(event.target.value)}
                        aria-invalid={Boolean(errors.proposed_on)}
                    />
                    <InputError
                        message={errors.proposed_on}
                        className="mt-1 text-[11px]"
                    />
                </div>
                <div className="field">
                    <label htmlFor="proposed-reason">Why (optional)</label>
                    <Textarea
                        id="proposed-reason"
                        value={reason}
                        maxLength={1000}
                        placeholder="e.g. The API documentation arrived a week late."
                        onChange={(event) => setReason(event.target.value)}
                    />
                    <InputError
                        message={errors.reason}
                        className="mt-1 text-[11px]"
                    />
                </div>
            </div>
            <Footer
                busy={busy}
                label="Send to the client"
                onSubmit={send}
                onCancel={() => onOpenChange(false)}
            />
        </Shell>
    );
}

/**
 * The client declining an ask to move a deadline, with an optional note.
 */
export function DeclineDeadlineDialog({
    open,
    onOpenChange,
    url,
    request,
    subject,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    request: DeadlineRequest;
    subject: string;
}) {
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    const decline = () => {
        setBusy(true);

        router.post(
            url,
            { decision_note: note },
            {
                ...IN_PLACE,
                onSuccess: () => onOpenChange(false),
                onError: setErrors,
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Shell
            open={open}
            onOpenChange={onOpenChange}
            title={`Keep the date for ${subject}`}
            description={`The student asked for ${shortDate(request.proposedOn)}. Declining keeps ${request.previousOn ? shortDate(request.previousOn) : 'the current date'}.`}
        >
            <div className="field">
                <label htmlFor="decision-note">
                    Note to the student (optional)
                </label>
                <Textarea
                    id="decision-note"
                    value={note}
                    maxLength={1000}
                    autoFocus
                    onChange={(event) => setNote(event.target.value)}
                />
                <InputError
                    message={errors.decision_note ?? errors.deadline}
                    className="mt-1 text-[11px]"
                />
            </div>
            <Footer
                busy={busy}
                label="Decline"
                onSubmit={decline}
                onCancel={() => onOpenChange(false)}
            />
        </Shell>
    );
}

/**
 * The client's Complete: accept the turnover and end the collaboration.
 *
 * Allowed with tasks still unverified, but never silently — the dialog says
 * how many, because completing is final.
 */
export function CompleteProjectDialog({
    open,
    onOpenChange,
    url,
    projectTitle,
    unverifiedCount,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    projectTitle: string;
    unverifiedCount: number;
}) {
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    const complete = () => {
        setBusy(true);

        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: setErrors,
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Shell
            open={open}
            onOpenChange={onOpenChange}
            title={`Complete “${projectTitle}”?`}
            description="This ends the collaboration. The project is marked complete and moves to your completed projects, and everyone on it is free to take on new work. It cannot be undone."
        >
            {unverifiedCount > 0 && (
                <div
                    role="alert"
                    style={{
                        fontSize: 12.5,
                        padding: '8px 10px',
                        borderRadius: 'var(--radius-md)',
                        background:
                            'color-mix(in srgb, var(--destructive) 10%, transparent)',
                    }}
                >
                    {unverifiedCount === 1
                        ? '1 task is not verified yet.'
                        : `${unverifiedCount} tasks are not verified yet.`}{' '}
                    Completing now accepts the project as it stands.
                </div>
            )}
            <InputError message={errors.project} className="mt-1 text-[11px]" />
            <Footer
                busy={busy}
                label="Complete project"
                onSubmit={complete}
                onCancel={() => onOpenChange(false)}
            />
        </Shell>
    );
}

/** A proof file the browser can draw as a picture. */
const PREVIEWABLE_IMAGE = /\.(jpe?g|png|webp)$/i;
const PDF_FILE = /\.pdf$/i;

/**
 * The file a student attached as proof, shown as what it is: the picture
 * itself for an image, a PDF document icon for a PDF, and a plain link for
 * anything else. Each opens the file in a new tab.
 *
 * Plain anchors and a plain img: the proof route streams the file (inline,
 * behind the project's own check), it is not an Inertia page.
 */
export function ProofFile({
    href,
    name,
}: {
    href: string;
    name: string | null;
}) {
    const label = name ?? 'Attachment';

    if (PREVIEWABLE_IMAGE.test(label)) {
        return (
            <a
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                title={`Open ${label}`}
                aria-label={`Open ${label}`}
                style={{ display: 'block', flex: 'none', lineHeight: 0 }}
            >
                <img
                    src={href}
                    alt={label}
                    loading="lazy"
                    style={{
                        display: 'block',
                        width: 180,
                        maxWidth: '100%',
                        height: 120,
                        objectFit: 'cover',
                        borderRadius: 8,
                        border: `1px solid ${MUTED(14)}`,
                        background: MUTED(6),
                    }}
                />
            </a>
        );
    }

    if (PDF_FILE.test(label)) {
        return (
            <a
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                title={`Open ${label}`}
                style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: 10,
                    maxWidth: '100%',
                    padding: '8px 12px 8px 8px',
                    borderRadius: 8,
                    border: `1px solid ${MUTED(14)}`,
                    background: MUTED(4),
                    textDecoration: 'none',
                    color: 'var(--color-text)',
                }}
            >
                <FilePdfIcon
                    size={30}
                    weight="duotone"
                    color="#c0392b"
                    style={{ flex: 'none' }}
                />
                <span style={{ minWidth: 0 }}>
                    <span
                        style={{
                            display: 'block',
                            fontSize: 12,
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                            maxWidth: 220,
                        }}
                    >
                        {label}
                    </span>
                    <span style={{ fontSize: 10.5, color: MUTED(55) }}>
                        PDF document
                    </span>
                </span>
            </a>
        );
    }

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            data-inline-link=""
            style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}
        >
            <PaperclipIcon />
            {label}
        </a>
    );
}
