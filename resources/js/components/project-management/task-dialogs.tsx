import { router } from '@inertiajs/react';
import { PaperclipIcon } from '@phosphor-icons/react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import InputError from '@/components/input-error';
import type { Phase, Task } from '@/components/project-management/types';
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
 */
export function TaskFormDialog({
    open,
    onOpenChange,
    url,
    method,
    phase,
    task,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    method: 'post' | 'patch';
    phase: Phase;
    task: Task | null;
}) {
    const [title, setTitle] = useState(task?.title ?? '');
    const [description, setDescription] = useState(task?.description ?? '');
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    const save = () => {
        setBusy(true);

        router[method](
            url,
            { title, description },
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
                        maxLength={160}
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
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    task: Task;
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
                    <label htmlFor="proof-file">File (optional)</label>
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
 * The client sending a submitted task back, with the reason.
 */
export function ReturnTaskDialog({
    open,
    onOpenChange,
    url,
    task,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    task: Task;
}) {
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    const send = () => {
        setBusy(true);

        router.post(
            url,
            { review_note: note },
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
            title={`Send “${task.title}” back`}
            description="The task goes back to the student as not done, with your note."
        >
            <div className="field">
                <label htmlFor="review-note">What needs to change</label>
                <Textarea
                    id="review-note"
                    value={note}
                    maxLength={2000}
                    autoFocus
                    onChange={(event) => setNote(event.target.value)}
                    aria-invalid={Boolean(errors.review_note)}
                />
                <InputError
                    message={errors.review_note ?? errors.task}
                    className="mt-1 text-[11px]"
                />
            </div>
            <Footer
                busy={busy}
                label="Send back"
                onSubmit={send}
                onCancel={() => onOpenChange(false)}
            />
        </Shell>
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

    const save = () => {
        setBusy(true);

        router.patch(
            url,
            { starts_on: startsOn, ends_on: endsOn },
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
                        onChange={(event) => setStartsOn(event.target.value)}
                        aria-invalid={Boolean(errors.starts_on)}
                    />
                    <InputError
                        message={errors.starts_on}
                        className="mt-1 text-[11px]"
                    />
                </div>
                <div className="field">
                    <label htmlFor="phase-ends">Ends</label>
                    <Input
                        id="phase-ends"
                        type="date"
                        value={endsOn}
                        min={startsOn || undefined}
                        onChange={(event) => setEndsOn(event.target.value)}
                        aria-invalid={Boolean(errors.ends_on)}
                    />
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
