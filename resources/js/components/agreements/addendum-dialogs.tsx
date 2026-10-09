import { router } from '@inertiajs/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
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
import { update as addendumUpdate } from '@/routes/agreements/addenda';
import {
    destroy as serviceDestroy,
    store as serviceStore,
    update as serviceUpdate,
} from '@/routes/agreements/addenda/services';
import type { AddendumEntry } from '@/types/agreements';

type Errors = Record<string, string>;

/** Re-read the addendum only, and keep the page where it was. */
const IN_PLACE = { preserveScroll: true, preserveState: true };

export type AddendumTarget = {
    teamSlug: string;
    agreementId: number;
    addendumId: number;
};

const argsFor = (target: AddendumTarget) => ({
    current_team: target.teamSlug,
    agreement: target.agreementId,
    addendum: target.addendumId,
});

/**
 * Section II's "Add service" and its edit twin: an Objective (the title) and
 * its Scope. When the down payment clears, each becomes one task in Project
 * Management's Objective & Scope, exactly like the memorandum's Section VII.
 */
export function AddendumServiceDialog({
    open,
    onOpenChange,
    target,
    entry,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    target: AddendumTarget;
    /** Null to add a new service. */
    entry: AddendumEntry | null;
}) {
    const [objective, setObjective] = useState(entry?.objective ?? '');
    const [scope, setScope] = useState(entry?.scope ?? '');
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    const save = () => {
        setBusy(true);

        const options = {
            ...IN_PLACE,
            onSuccess: () => onOpenChange(false),
            onError: setErrors,
            onFinish: () => setBusy(false),
        };
        const data = { objective, scope };

        if (entry === null) {
            router.post(serviceStore.url(argsFor(target)), data, options);
        } else {
            router.patch(
                serviceUpdate.url({ ...argsFor(target), service: entry.id }),
                data,
                options,
            );
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {entry === null
                            ? 'Add a service to Section II'
                            : 'Edit this service'}
                    </DialogTitle>
                    <DialogDescription>
                        Name the objective and describe its scope. Once the down
                        payment clears, it becomes one task in Project
                        Management’s Objective &amp; Scope.
                    </DialogDescription>
                </DialogHeader>

                <div style={{ display: 'grid', gap: 12 }}>
                    <div className="field">
                        <label htmlFor="addendum-objective">
                            Objective (title)
                        </label>
                        <Input
                            id="addendum-objective"
                            value={objective}
                            maxLength={120}
                            autoFocus
                            placeholder="e.g. Post-Deployment Maintenance"
                            aria-invalid={Boolean(errors.objective)}
                            onChange={(event) =>
                                setObjective(event.target.value)
                            }
                        />
                        <InputError
                            message={errors.objective}
                            className="mt-1 text-[11px]"
                        />
                    </div>
                    <div className="field">
                        <label htmlFor="addendum-scope">
                            Scope (description)
                        </label>
                        <Textarea
                            id="addendum-scope"
                            value={scope}
                            maxLength={2000}
                            rows={5}
                            placeholder="What this extended service covers and delivers."
                            aria-invalid={Boolean(errors.scope)}
                            onChange={(event) => setScope(event.target.value)}
                        />
                        <InputError
                            message={errors.scope}
                            className="mt-1 text-[11px]"
                        />
                    </div>
                </div>

                {/* A direct child of DialogContent, so gap-3 only — see .ai/rules/components.md. */}
                <DialogFooter className="gap-3">
                    <Btn variant="primary" disabled={busy} onClick={save}>
                        {busy && <Spinner />}
                        {entry === null ? 'Add service' : 'Save changes'}
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

/** Remove one of your own Section II services. */
export function RemoveAddendumServiceDialog({
    open,
    onOpenChange,
    target,
    entry,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    target: AddendumTarget;
    entry: AddendumEntry;
}) {
    const [busy, setBusy] = useState(false);

    const remove = () => {
        setBusy(true);
        router.delete(
            serviceDestroy.url({ ...argsFor(target), service: entry.id }),
            {
                ...IN_PLACE,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove this service?</DialogTitle>
                    <DialogDescription>
                        “{entry.objective}” comes out of Section II.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-3">
                    <Btn variant="primary" disabled={busy} onClick={remove}>
                        {busy && <Spinner />}
                        Remove
                    </Btn>
                    <Btn
                        variant="ghost"
                        disabled={busy}
                        onClick={() => onOpenChange(false)}
                    >
                        Keep it
                    </Btn>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Section IV's target amount: the one number either party sets, in whole
 * pesos up to the limit. The two milestones are cut from it.
 */
export function AddendumAmountDialog({
    open,
    onOpenChange,
    target,
    current,
    limits,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    target: AddendumTarget;
    current: number | null;
    limits: { min: number; max: number; downPaymentPercent: number };
}) {
    const [amount, setAmount] = useState(
        current === null ? '' : String(current),
    );
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    const pesos = Number(amount);
    const isNumber = amount !== '' && Number.isFinite(pesos);
    const down = isNumber
        ? Math.floor(pesos * limits.downPaymentPercent) / 100
        : null;

    const save = () => {
        setBusy(true);
        router.patch(
            addendumUpdate.url(argsFor(target)),
            { total_amount: amount === '' ? null : pesos },
            {
                ...IN_PLACE,
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
                    <DialogTitle>Set the target amount</DialogTitle>
                    <DialogDescription>
                        The total for the extended work, in pesos, up to ₱
                        {limits.max.toLocaleString()}. Milestone 1 (
                        {limits.downPaymentPercent}%) is due once both of you
                        sign; Milestone 2 ({100 - limits.downPaymentPercent}%)
                        once the work is handed in.
                    </DialogDescription>
                </DialogHeader>

                <div className="field">
                    <label htmlFor="addendum-amount">Target amount (₱)</label>
                    <Input
                        id="addendum-amount"
                        type="number"
                        inputMode="numeric"
                        min={limits.min}
                        max={limits.max}
                        step={1}
                        value={amount}
                        autoFocus
                        aria-invalid={Boolean(errors.total_amount)}
                        onChange={(event) => setAmount(event.target.value)}
                    />
                    <InputError
                        message={errors.total_amount}
                        className="mt-1 text-[11px]"
                    />
                    {down !== null && pesos > 0 && pesos <= limits.max && (
                        <div style={{ marginTop: 6, fontSize: 12 }}>
                            Milestone 1: ₱
                            {down.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                            })}{' '}
                            · Milestone 2: ₱
                            {(pesos - down).toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                            })}
                        </div>
                    )}
                </div>

                <DialogFooter className="gap-3">
                    <Btn variant="primary" disabled={busy} onClick={save}>
                        {busy && <Spinner />}
                        Save amount
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
