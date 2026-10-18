import { router } from '@inertiajs/react';
import {
    HandPalmIcon,
    SignOutIcon,
    StarIcon,
    WarningCircleIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Textarea } from '@/components/sdpc/input';
import { Panel } from '@/components/sdpc/panel';
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
    accept as acceptRequest,
    decline as declineRequest,
    destroy as withdrawRequest,
} from '@/routes/agreements/requests';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Errors = Record<string, string>;

/**
 * A termination request waiting for an answer, or a denial, which is final
 * the moment it is sent (PresentContractRequest).
 */
export type ContractRequestView = {
    id: number;
    type: 'denial' | 'termination';
    status: 'pending' | 'accepted' | 'declined' | 'withdrawn' | 'denied';
    subject: 'agreement' | 'addendum';
    party: 'client' | 'student';
    partyLabel: string;
    requestedBy: string | null;
    reason: string;
    requestedAt: string | null;
    /** The viewer is the one who asked (or denied). */
    byViewer: boolean;
    canDecide: boolean;
    canWithdraw: boolean;
    /** The client agreeing to a termination the student asked for rates the work. */
    needsRating: boolean;
};

/** What the request would do, in words both sides read. */
function asking(
    request: Pick<ContractRequestView, 'type' | 'subject'>,
): string {
    if (request.type === 'termination') {
        return 'end the collaboration early';
    }

    return request.subject === 'addendum'
        ? 'deny the project extension addendum'
        : 'deny the Memorandum of Agreement';
}

/** What a denial turned down. */
function denied(request: Pick<ContractRequestView, 'subject'>): string {
    return request.subject === 'addendum'
        ? 'the project extension addendum'
        : 'the Memorandum of Agreement';
}

/**
 * "Deny": turn an unsigned MoA or Addendum down, with a required reason
 * (owner, 2026-10-18). It is final: cancelled at once, and the other side
 * can neither decline it nor deny it back.
 */
export function DenyButton({
    url,
    subject,
}: {
    url: string;
    subject: 'agreement' | 'addendum';
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Btn variant="ghost" onClick={() => setOpen(true)}>
                <HandPalmIcon />
                {subject === 'addendum' ? 'Deny addendum' : 'Deny agreement'}
            </Btn>
            {open && (
                <AskDialog
                    open
                    onOpenChange={setOpen}
                    url={url}
                    title={
                        subject === 'addendum'
                            ? 'Deny this addendum?'
                            : 'Deny this agreement?'
                    }
                    description={
                        subject === 'addendum'
                            ? 'Say why. A denial is final: the extension is cancelled at once, the project cannot be extended again, and the project carries on as it is. This cannot be undone.'
                            : 'Say why. A denial is final: the agreement is cancelled at once and both of you are free to start something new. This cannot be undone.'
                    }
                    submitLabel={
                        subject === 'addendum'
                            ? 'Deny addendum'
                            : 'Deny agreement'
                    }
                    rates={false}
                />
            )}
        </>
    );
}

/**
 * "Request termination": ask to end a running collaboration, with a reason
 * (owner, 2026-10-18). It ends, and the project completes, only once the
 * other side agrees. A client asking rates the work now.
 */
export function TerminationButton({
    url,
    rates,
}: {
    url: string;
    rates: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Btn
                variant="ghost"
                onClick={() => setOpen(true)}
                data-test="request-termination"
            >
                <SignOutIcon />
                Request termination
            </Btn>
            {open && (
                <AskDialog
                    open
                    onOpenChange={setOpen}
                    url={url}
                    title="Request to end the collaboration?"
                    description={
                        rates
                            ? 'Say why and rate the work so far. The student reads your reason and accepts or declines; once they agree, the project is completed with your rating and everyone is free to take on new work.'
                            : 'Say why. The client reads your reason and accepts or declines; once they agree, the project is completed and everyone is free to take on new work.'
                    }
                    submitLabel="Send request"
                    rates={rates}
                />
            )}
        </>
    );
}

/** The reason (and, for a client ending the build, the rating) to send. */
function AskDialog({
    open,
    onOpenChange,
    url,
    title,
    description,
    submitLabel,
    rates,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    title: string;
    description: string;
    submitLabel: string;
    rates: boolean;
}) {
    const [reason, setReason] = useState('');
    const [rating, setRating] = useState(0);
    const [feedback, setFeedback] = useState('');
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    const send = () => {
        setBusy(true);
        router.post(url, rates ? { reason, rating, feedback } : { reason }, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: setErrors,
            onFinish: () => setBusy(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <div style={{ display: 'grid', gap: 12 }}>
                    <div className="field">
                        <label htmlFor="contract-reason">
                            Reason (required)
                        </label>
                        <Textarea
                            id="contract-reason"
                            value={reason}
                            maxLength={2000}
                            rows={4}
                            autoFocus
                            aria-invalid={Boolean(errors.reason)}
                            onChange={(event) => setReason(event.target.value)}
                        />
                        <InputError
                            message={errors.reason}
                            className="mt-1 text-[11px]"
                        />
                    </div>
                    {rates && (
                        <RatingFields
                            rating={rating}
                            onRating={setRating}
                            feedback={feedback}
                            onFeedback={setFeedback}
                            errors={errors}
                        />
                    )}
                    <InputError message={errors.request} />
                </div>

                <DialogFooter className="gap-3">
                    <Btn
                        variant="primary"
                        disabled={
                            busy ||
                            reason.trim().length < 5 ||
                            (rates && rating === 0)
                        }
                        onClick={send}
                    >
                        {busy && <Spinner />}
                        {submitLabel}
                    </Btn>
                    <Btn
                        variant="ghost"
                        disabled={busy}
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Btn>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * A request waiting for an answer: who asked, why, and the viewer's way to
 * answer it (accept or decline) or take it back. A denial only reads: it is
 * final, so nobody answers it.
 */
export function ContractRequestBanner({
    request,
    teamSlug,
    agreementId,
}: {
    request: ContractRequestView;
    teamSlug: string;
    agreementId: number;
}) {
    const [dialog, setDialog] = useState<'accept' | 'decline' | null>(null);
    const [busy, setBusy] = useState(false);
    const args = {
        current_team: teamSlug,
        agreement: agreementId,
        contractRequest: request.id,
    };

    const withdraw = () => {
        setBusy(true);
        router.delete(withdrawRequest.url(args), {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };

    return (
        <Panel
            padding="lg"
            gap="sm"
            data-test="contract-request-banner"
            style={{
                background:
                    'color-mix(in srgb, var(--destructive) 7%, var(--color-surface))',
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-start',
                    gap: 10,
                }}
            >
                <WarningCircleIcon
                    style={{
                        fontSize: 20,
                        flex: 'none',
                        color: 'var(--destructive)',
                    }}
                />
                <div style={{ display: 'grid', gap: 4, minWidth: 0 }}>
                    <span style={{ fontSize: 13.5, fontWeight: 600 }}>
                        {request.type === 'denial'
                            ? request.byViewer
                                ? `You denied ${denied(request)}.`
                                : `The ${request.partyLabel.toLowerCase()}${request.requestedBy ? ` (${request.requestedBy})` : ''} denied ${denied(request)}.`
                            : request.canWithdraw
                              ? `You asked to ${asking(request)}.`
                              : `The ${request.partyLabel.toLowerCase()}${request.requestedBy ? ` (${request.requestedBy})` : ''} asked to ${asking(request)}.`}
                    </span>
                    <span
                        style={{
                            fontSize: 13,
                            color: MUTED(75),
                            whiteSpace: 'pre-line',
                            overflowWrap: 'anywhere',
                        }}
                    >
                        “{request.reason}”
                    </span>
                    <span style={{ fontSize: 11.5, color: MUTED(55) }}>
                        {request.requestedAt}
                        {request.type === 'denial'
                            ? ' · this denial is final'
                            : request.canWithdraw
                              ? ' · waiting for the other side to answer'
                              : ''}
                    </span>
                </div>
            </div>
            {(request.canDecide || request.canWithdraw) && (
                <div
                    style={{
                        display: 'flex',
                        gap: 8,
                        flexWrap: 'wrap',
                        justifyContent: 'flex-end',
                    }}
                >
                    {request.canDecide && (
                        <>
                            <Btn
                                variant="ghost"
                                onClick={() => setDialog('decline')}
                            >
                                Decline
                            </Btn>
                            <Btn
                                variant="primary"
                                onClick={() => setDialog('accept')}
                            >
                                Accept
                            </Btn>
                        </>
                    )}
                    {request.canWithdraw && (
                        <Btn variant="ghost" disabled={busy} onClick={withdraw}>
                            {busy && <Spinner />}
                            Withdraw request
                        </Btn>
                    )}
                </div>
            )}

            {dialog === 'accept' && (
                <AnswerDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={acceptRequest.url(args)}
                    title={
                        request.type === 'termination'
                            ? 'Agree to end the collaboration?'
                            : 'Accept this denial?'
                    }
                    description={
                        request.type === 'termination'
                            ? 'The project is completed now and everyone on it is free to take on new work. Any extension still open is cancelled. This cannot be undone.'
                            : request.subject === 'addendum'
                              ? 'The extension is cancelled. The Memorandum of Agreement and the project carry on as they are.'
                              : 'The agreement is cancelled. The student is free to apply elsewhere and the business can hire for the posting again. This cannot be undone.'
                    }
                    submitLabel={
                        request.type === 'termination'
                            ? 'End the collaboration'
                            : 'Accept denial'
                    }
                    mode={request.needsRating ? 'rate' : 'confirm'}
                />
            )}
            {dialog === 'decline' && (
                <AnswerDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    url={declineRequest.url(args)}
                    title="Decline this request?"
                    description="Everything carries on as before. You can say why (optional)."
                    submitLabel="Decline"
                    mode="note"
                />
            )}
        </Panel>
    );
}

/** Accept (confirm, or rate for a client ending the build) or decline (with a note). */
function AnswerDialog({
    open,
    onOpenChange,
    url,
    title,
    description,
    submitLabel,
    mode,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    title: string;
    description: string;
    submitLabel: string;
    mode: 'confirm' | 'rate' | 'note';
}) {
    const [rating, setRating] = useState(0);
    const [feedback, setFeedback] = useState('');
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    const send = () => {
        setBusy(true);
        router.post(
            url,
            mode === 'rate'
                ? { rating, feedback }
                : mode === 'note'
                  ? { note }
                  : {},
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: setErrors,
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                {mode === 'rate' && (
                    <RatingFields
                        rating={rating}
                        onRating={setRating}
                        feedback={feedback}
                        onFeedback={setFeedback}
                        errors={errors}
                    />
                )}
                {mode === 'note' && (
                    <div className="field">
                        <label htmlFor="contract-note">Why (optional)</label>
                        <Textarea
                            id="contract-note"
                            value={note}
                            maxLength={2000}
                            rows={3}
                            onChange={(event) => setNote(event.target.value)}
                        />
                    </div>
                )}
                <InputError message={errors.request ?? errors.project} />

                <DialogFooter className="gap-3">
                    <Btn
                        variant="primary"
                        disabled={busy || (mode === 'rate' && rating === 0)}
                        onClick={send}
                    >
                        {busy && <Spinner />}
                        {submitLabel}
                    </Btn>
                    <Btn
                        variant="ghost"
                        disabled={busy}
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Btn>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** 1-5 stars (required) and optional feedback: completing is rating. */
function RatingFields({
    rating,
    onRating,
    feedback,
    onFeedback,
    errors,
}: {
    rating: number;
    onRating: (rating: number) => void;
    feedback: string;
    onFeedback: (feedback: string) => void;
    errors: Errors;
}) {
    return (
        <div style={{ display: 'grid', gap: 12 }}>
            <div className="field">
                <label id="contract-rating-label">Rating (required)</label>
                <div
                    role="radiogroup"
                    aria-labelledby="contract-rating-label"
                    style={{ display: 'flex', gap: 4 }}
                >
                    {[1, 2, 3, 4, 5].map((star) => (
                        <button
                            key={star}
                            type="button"
                            role="radio"
                            aria-checked={rating === star}
                            aria-label={`${star} star${star === 1 ? '' : 's'}`}
                            onClick={() => onRating(star)}
                            style={{
                                border: 0,
                                background: 'none',
                                padding: 2,
                                cursor: 'pointer',
                                color: star <= rating ? '#e0a526' : MUTED(35),
                            }}
                        >
                            <StarIcon
                                size={24}
                                weight={star <= rating ? 'fill' : 'regular'}
                            />
                        </button>
                    ))}
                </div>
                <InputError
                    message={errors.rating}
                    className="mt-1 text-[11px]"
                />
            </div>
            <div className="field">
                <label htmlFor="contract-feedback">Feedback (optional)</label>
                <Textarea
                    id="contract-feedback"
                    value={feedback}
                    maxLength={2000}
                    rows={3}
                    onChange={(event) => onFeedback(event.target.value)}
                />
            </div>
        </div>
    );
}
