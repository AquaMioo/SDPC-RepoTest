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
import {
    destroy as requirementDestroy,
    store as requirementStore,
    update as requirementUpdate,
} from '@/routes/agreements/requirements';
import {
    destroy as serviceDestroy,
    store as serviceStore,
    update as serviceUpdate,
} from '@/routes/agreements/services';
import type { MoaEntry, MoaSection } from '@/types/agreements';

type Errors = Record<string, string>;

/** Re-read the agreement only, and keep the page where it was. */
const IN_PLACE = { preserveScroll: true, preserveState: true };

type Target = {
    teamSlug: string;
    agreementId: number;
    section: MoaSection;
};

/**
 * "Add requirement" and its edit twin, for one section of the memorandum.
 *
 * Section VII asks for a service: an Objective (its title) and a Scope (what
 * it covers); when the work starts the service becomes one item in Project
 * Management's Objective & Scope, the objective its title and the scope its
 * description. Every other
 * section takes one line, appended after the school's own wording. Either
 * way it goes in under the writer's name, and only they may change it.
 */
export function MemorandumEntryDialog({
    open,
    onOpenChange,
    teamSlug,
    agreementId,
    section,
    entry,
}: Target & {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Null to add a new entry. */
    entry: MoaEntry | null;
}) {
    const isService = section.key === 'services';

    const [objective, setObjective] = useState(entry?.title ?? '');
    const [body, setBody] = useState(entry?.body ?? '');
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState(false);

    const args = { current_team: teamSlug, agreement: agreementId };

    const save = () => {
        setBusy(true);

        const options = {
            ...IN_PLACE,
            onSuccess: () => onOpenChange(false),
            onError: setErrors,
            onFinish: () => setBusy(false),
        };

        if (isService) {
            const data = { objective, scope: body };

            if (entry === null) {
                router.post(serviceStore.url(args), data, options);
            } else {
                router.patch(
                    serviceUpdate.url({ ...args, service: entry.id }),
                    data,
                    options,
                );
            }

            return;
        }

        if (entry === null) {
            router.post(
                requirementStore.url(args),
                { section: section.key, body },
                options,
            );
        } else {
            router.patch(
                requirementUpdate.url({ ...args, requirement: entry.id }),
                { body },
                options,
            );
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isService
                            ? entry === null
                                ? 'Add a service to Section VII'
                                : 'Edit this service'
                            : entry === null
                              ? `Add a requirement to ${section.numeral}. ${section.heading}`
                              : 'Edit your requirement'}
                    </DialogTitle>
                    <DialogDescription>
                        {isService
                            ? 'Name the objective and describe its scope. When the work starts, the service becomes one item in Project Management’s Objective & Scope: the objective as its title, the scope as its description.'
                            : 'It is added after the section’s own wording, which nobody can change. Only you can edit or remove it, and only until somebody signs.'}
                    </DialogDescription>
                </DialogHeader>

                <div style={{ display: 'grid', gap: 12 }}>
                    {isService && (
                        <div className="field">
                            <label htmlFor="moa-objective">
                                Objective (title)
                            </label>
                            <Input
                                id="moa-objective"
                                value={objective}
                                maxLength={120}
                                autoFocus
                                placeholder="e.g. Inventory and supplier module"
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
                    )}
                    <div className="field">
                        <label htmlFor="moa-body">
                            {isService ? 'Scope (description)' : 'Requirement'}
                        </label>
                        <Textarea
                            id="moa-body"
                            value={body}
                            maxLength={2000}
                            autoFocus={!isService}
                            rows={5}
                            placeholder={
                                isService
                                    ? 'What this service covers and delivers.'
                                    : section.addition?.placeholder
                            }
                            aria-invalid={Boolean(
                                isService ? errors.scope : errors.body,
                            )}
                            onChange={(event) => setBody(event.target.value)}
                        />
                        <InputError
                            message={isService ? errors.scope : errors.body}
                            className="mt-1 text-[11px]"
                        />
                    </div>
                </div>

                {/* A direct child of DialogContent, so gap-3 only — see .ai/rules/components.md. */}
                <DialogFooter className="gap-3">
                    <Btn variant="primary" disabled={busy} onClick={save}>
                        {busy && <Spinner />}
                        {entry === null
                            ? isService
                                ? 'Add service'
                                : 'Add requirement'
                            : 'Save changes'}
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
 * Confirm removing an entry the reader added.
 */
export function RemoveEntryDialog({
    open,
    onOpenChange,
    teamSlug,
    agreementId,
    section,
    entry,
}: Target & {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entry: MoaEntry;
}) {
    const [busy, setBusy] = useState(false);

    const args = { current_team: teamSlug, agreement: agreementId };
    const isService = section.key === 'services';

    const remove = () => {
        setBusy(true);

        router.delete(
            isService
                ? serviceDestroy.url({ ...args, service: entry.id })
                : requirementDestroy.url({ ...args, requirement: entry.id }),
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
                    <DialogTitle>
                        {isService
                            ? `Remove “${entry.title}”?`
                            : 'Remove this requirement?'}
                    </DialogTitle>
                    <DialogDescription>
                        {isService
                            ? 'Its objective and scope leave Section VII and the printed copy.'
                            : 'It leaves the memorandum and the printed copy.'}
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
                        Cancel
                    </Btn>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
