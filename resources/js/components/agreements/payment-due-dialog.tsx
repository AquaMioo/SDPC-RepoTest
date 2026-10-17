import { Link, usePage } from '@inertiajs/react';
import { WalletIcon } from '@phosphor-icons/react';
import { useEffect, useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type PaymentDueStage = 'down' | 'final';

type SharedProps = { auth?: { user?: { id: number } | null } };

/**
 * The popups that tell both sides a milestone is due (owner, 2026-10-18):
 * the down payment once both have signed the addendum, and the final balance
 * once the student has handed in all the extended work, before the client can
 * complete the project. Each person sees each one once per addendum; it is
 * remembered on this device.
 */
export function PaymentDueDialog({
    addendumId,
    reference,
    stage,
    amountLabel,
    isClient,
    addendumUrl,
}: {
    addendumId: number;
    reference: string;
    /** Which milestone is due now, or null when neither is. */
    stage: PaymentDueStage | null;
    amountLabel: string | null;
    isClient: boolean;
    /** Where "Pay now" goes; omitted on the addendum screen itself. */
    addendumUrl?: string;
}) {
    const userId = usePage<SharedProps>().props.auth?.user?.id ?? 0;
    const key = `sdpc:payment-due:${userId}:${addendumId}:${stage}`;
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (stage === null) {
            return;
        }

        let seen = false;

        try {
            seen = window.localStorage.getItem(key) !== null;
        } catch {
            /* Storage blocked: show it, it simply is not remembered. */
        }

        if (seen) {
            return;
        }

        /* After the page has painted, so the popup lands on a settled screen. */
        const timer = window.setTimeout(() => setOpen(true), 0);

        return () => window.clearTimeout(timer);
    }, [key, stage]);

    if (stage === null) {
        return null;
    }

    const dismiss = () => {
        try {
            window.localStorage.setItem(key, '1');
        } catch {
            /* Not remembered: it may show once more next time. */
        }

        setOpen(false);
    };

    const amount = amountLabel ? ` (${amountLabel})` : '';

    return (
        <Dialog open={open} onOpenChange={(next) => !next && dismiss()}>
            <DialogContent data-test={`payment-due-${stage}`}>
                <DialogHeader>
                    <DialogTitle>
                        {stage === 'down'
                            ? 'Down payment required'
                            : 'Final balance required'}
                    </DialogTitle>
                    <DialogDescription>
                        {stage === 'down'
                            ? `Both sides have signed addendum ${reference}. The client must pay Milestone 1, the down payment${amount}, before the extended work can start.`
                            : `The student has handed in all the extended work on addendum ${reference}. The client must pay Milestone 2, the remaining balance${amount}, before the project can be completed and the collaboration closed.`}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-3">
                    {isClient && addendumUrl && (
                        <Btn asChild variant="primary" onClick={dismiss}>
                            <Link href={addendumUrl}>
                                <WalletIcon />
                                Go to payment
                            </Link>
                        </Btn>
                    )}
                    <Btn
                        variant={isClient && addendumUrl ? 'ghost' : 'primary'}
                        onClick={dismiss}
                    >
                        {isClient ? 'Got it' : 'OK'}
                    </Btn>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
