import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    DownloadSimpleIcon,
    LockSimpleIcon,
    PrinterIcon,
    ReceiptIcon,
    WalletIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';
import { toast } from 'sonner';

import { AddendumDocument } from '@/components/agreements/addendum-document';
import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Input } from '@/components/sdpc/input';
import { Panel, PanelDivider, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import WarningNotice from '@/components/sdpc/warning-notice';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { projectManagement } from '@/routes';
import { show as agreementShow } from '@/routes/agreements';
import {
    destroy as addendumDestroy,
    printable as addendumPrintable,
    records as addendumRecords,
    template as addendumTemplate,
} from '@/routes/agreements/addenda';
import { checkout as paymentCheckout } from '@/routes/agreements/addenda/payments';
import { store as signatureStore } from '@/routes/agreements/addenda/signatures';
import { edit as profileEdit } from '@/routes/profile';
import type { Addendum, AddendumPayment } from '@/types/agreements';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const TAG_VARIANT: Record<
    string,
    'accent' | 'accent-2' | 'neutral' | 'outline'
> = {
    accent: 'accent',
    outline: 'outline',
    neutral: 'neutral',
};

/**
 * The Payment & Project Extension Addendum screen, for both parties.
 *
 * The document itself, as paper, with Section II's "Add service" and Section
 * IV's target amount open to both sides until somebody signs; then the
 * signature panel; then, once both have signed, the two milestones the client
 * pays through PayMongo. The memorandum it extends is untouched and one click
 * away.
 */
export default function AddendumShow({ addendum }: { addendum: Addendum }) {
    const team = useCurrentTeam();
    const { viewer } = addendum;
    const args = {
        current_team: team.slug,
        agreement: addendum.agreementId,
        addendum: addendum.id,
    };

    return (
        <>
            <Head title={`Addendum ${addendum.reference}`} />

            <div
                style={{
                    maxWidth: 'clamp(1000px, 100vw - 320px, 1600px)',
                    margin: '0 auto',
                    padding: '28px clamp(16px, 4vw, 32px) 72px',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        gap: 12,
                        flexWrap: 'wrap',
                        marginBottom: 6,
                    }}
                >
                    <div style={{ marginRight: 'auto' }}>
                        <h3 style={{ margin: 0 }}>
                            Payment &amp; Project Extension Addendum
                        </h3>
                        <div style={{ fontSize: 12.5, color: MUTED(68) }}>
                            {addendum.projectTitle} · {addendum.reference} ·
                            addendum to contract {addendum.agreementReference}
                        </div>
                    </div>

                    <Tag
                        variant={
                            TAG_VARIANT[addendum.statusVariant] ?? 'neutral'
                        }
                    >
                        {addendum.statusLabel}
                    </Tag>

                    {/* The form as SDPC issues it, unsigned: a file, so a plain link. */}
                    <a
                        href={addendumTemplate.url(args)}
                        download
                        title="Download the blank SDPC Addendum form (PDF)"
                        data-test="download-addendum-template"
                        className="tag-link"
                        onClick={() =>
                            toast.success('Downloading the blank addendum…', {
                                description: `${addendum.reference} Addendum.pdf`,
                            })
                        }
                    >
                        <Tag variant="outline">
                            <DownloadSimpleIcon style={{ marginRight: 5 }} />
                            Blank form · PDF
                        </Tag>
                    </a>

                    {/* Filled in, signed or not: print it or save it as a PDF. */}
                    <Btn asChild variant="primary">
                        <Link href={addendumPrintable.url(args)}>
                            <PrinterIcon />
                            Print or download
                        </Link>
                    </Btn>
                </div>

                <p
                    style={{
                        margin: '10px 0 18px',
                        fontSize: 12.5,
                        lineHeight: 1.6,
                        color: MUTED(68),
                        maxWidth: 820,
                    }}
                >
                    {viewer.canEdit
                        ? 'This addendum sits beside your Memorandum of Agreement and does not change it. Both of you can add the extended services to Section II and set Section IV’s target amount; everything else is fixed wording. Both GCash accounts must be registered in Settings, and everything locks the moment either of you signs.'
                        : 'The addendum’s wording is fixed and what the parties added is shown in place. The Memorandum of Agreement it extends is unchanged.'}
                </p>

                {viewer.needsGcash && (
                    <WarningNotice style={{ marginBottom: 14 }}>
                        {viewer.party === 'client' &&
                        !viewer.isClientRepresentative
                            ? `${viewer.clientRepresentativeName} (the client representative named on this addendum) has not registered a GCash account yet. It must be in Settings before either side can sign.`
                            : 'Register your GCash account before signing: '}
                        {(viewer.party === 'student' ||
                            viewer.isClientRepresentative) && (
                            <Link href={profileEdit.url()} data-inline-link="">
                                Settings → GCash account
                            </Link>
                        )}
                    </WarningNotice>
                )}

                <AddendumDocument
                    addendum={addendum}
                    editing={
                        viewer.canEdit
                            ? {
                                  teamSlug: team.slug,
                                  agreementId: addendum.agreementId,
                                  addendumId: addendum.id,
                              }
                            : null
                    }
                />

                <div
                    style={{
                        marginTop: 16,
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 16,
                    }}
                >
                    {(addendum.status === 'draft' ||
                        addendum.status === 'awaiting_signatures') && (
                        <SigningPanel addendum={addendum} args={args} />
                    )}

                    {addendum.payments.length > 0 && (
                        <PaymentsPanel addendum={addendum} args={args} />
                    )}

                    {addendum.signatures.length > 0 && (
                        <Panel gap="md" style={{ padding: '18px 24px' }}>
                            <PanelKicker>Signature log</PanelKicker>
                            {addendum.signatures.map((signature) => (
                                <div
                                    key={signature.party}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 10,
                                        fontSize: 12.5,
                                        flexWrap: 'wrap',
                                    }}
                                >
                                    <span style={{ marginRight: 'auto' }}>
                                        {signature.signedName} ·{' '}
                                        {signature.partyLabel}
                                    </span>
                                    <span style={{ color: MUTED(68) }}>
                                        {signature.signedAt}
                                    </span>
                                </div>
                            ))}
                        </Panel>
                    )}

                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <Btn asChild variant="ghost">
                            <Link
                                href={agreementShow.url({
                                    current_team: team.slug,
                                    agreement: addendum.agreementId,
                                })}
                            >
                                <ArrowLeftIcon />
                                Back to the agreement
                            </Link>
                        </Btn>
                        <Btn asChild variant="ghost">
                            <Link
                                href={projectManagement.url(team.slug, {
                                    query: { agreement: addendum.agreementId },
                                })}
                            >
                                Project Management
                            </Link>
                        </Btn>
                    </div>
                </div>
            </div>
        </>
    );
}

type Args = { current_team: string; agreement: number; addendum: number };

/**
 * Section IX: each side types its name over the line and confirms it read the
 * addendum. Says first what still stops anyone signing, the way the
 * memorandum's signature panel does; the server refuses the same things.
 */
function SigningPanel({ addendum, args }: { addendum: Addendum; args: Args }) {
    const { viewer } = addendum;
    const [confirmCancel, setConfirmCancel] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const form = useForm<{ signed_name: string; agreed: boolean }>({
        signed_name: '',
        agreed: false,
    });

    const sign = () =>
        form.post(signatureStore.url(args), { preserveScroll: true });

    const cancel = () => {
        setCancelling(true);
        router.delete(addendumDestroy.url(args), {
            onFinish: () => setCancelling(false),
        });
    };

    return (
        <Panel
            padding="lg"
            gap="lg"
            style={{
                padding: '22px 24px',
                background:
                    'color-mix(in srgb, var(--color-accent) 8%, var(--color-surface))',
            }}
        >
            <h6 style={{ margin: 0 }}>Sign the addendum</h6>

            {viewer.party === null && (
                <div style={{ fontSize: 13, color: MUTED(70) }}>
                    Only the student team lead and the client sign this
                    addendum.
                </div>
            )}

            {viewer.hasSigned && (
                <div style={{ fontSize: 13 }}>
                    You signed. It is waiting for the other side’s signature.
                </div>
            )}

            {viewer.signingBlockedBy && !viewer.hasSigned && (
                <div
                    role="status"
                    style={{
                        padding: '10px 12px',
                        borderRadius: 8,
                        fontSize: 12.5,
                        lineHeight: 1.5,
                        color: 'var(--destructive)',
                        background:
                            'color-mix(in srgb, var(--destructive) 8%, transparent)',
                    }}
                >
                    Signing is blocked: {viewer.signingBlockedBy}
                </div>
            )}

            {viewer.canSign && (
                <>
                    <label
                        style={{
                            display: 'flex',
                            gap: 11,
                            alignItems: 'flex-start',
                            fontSize: 12.5,
                            lineHeight: 1.55,
                            cursor: 'pointer',
                        }}
                    >
                        <input
                            type="checkbox"
                            style={{
                                accentColor: 'var(--color-accent)',
                                width: 16,
                                height: 16,
                                marginTop: 1,
                                flex: 'none',
                            }}
                            checked={form.data.agreed}
                            onChange={(event) =>
                                form.setData('agreed', event.target.checked)
                            }
                        />
                        I have read this Addendum, including the two-tier
                        payment terms in Section IV and the asset lock in
                        Section VI, and I agree to it. My electronic signature
                        is valid under R.A. 8792.
                    </label>
                    <InputError
                        message={form.errors.agreed}
                        className="text-[11px]"
                    />

                    <PanelDivider />

                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'flex-end',
                            gap: 12,
                            flexWrap: 'wrap',
                        }}
                    >
                        <div
                            className="field"
                            style={{ maxWidth: 320, flex: 1 }}
                        >
                            <label htmlFor="addendum-signed-name">
                                Type your full name to sign
                            </label>
                            <Input
                                id="addendum-signed-name"
                                value={form.data.signed_name}
                                maxLength={120}
                                autoComplete="off"
                                placeholder="Your name, as you would write it"
                                aria-invalid={Boolean(form.errors.signed_name)}
                                onChange={(event) =>
                                    form.setData(
                                        'signed_name',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.signed_name}
                                className="mt-1 text-[11px]"
                            />
                        </div>
                        <Btn
                            variant="primary"
                            disabled={
                                form.processing ||
                                viewer.signingBlockedBy !== null ||
                                !form.data.agreed ||
                                form.data.signed_name.trim() === ''
                            }
                            onClick={sign}
                        >
                            {form.processing && <Spinner />}
                            Sign addendum
                        </Btn>
                    </div>
                </>
            )}

            {viewer.canCancel && (
                <div>
                    <Btn variant="ghost" onClick={() => setConfirmCancel(true)}>
                        Cancel the extension
                    </Btn>
                </div>
            )}

            <Dialog open={confirmCancel} onOpenChange={setConfirmCancel}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Cancel this project extension?
                        </DialogTitle>
                        <DialogDescription>
                            Addendum {addendum.reference} is withdrawn and
                            nobody can sign it. The Memorandum of Agreement and
                            the project carry on as they are.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-3">
                        <Btn
                            variant="primary"
                            disabled={cancelling}
                            onClick={cancel}
                        >
                            {cancelling && <Spinner />}
                            Cancel the extension
                        </Btn>
                        <Btn
                            variant="ghost"
                            disabled={cancelling}
                            onClick={() => setConfirmCancel(false)}
                        >
                            Keep it
                        </Btn>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Panel>
    );
}

/**
 * Section IV in motion: the two milestones, what each is waiting on, and for
 * the client the button that opens PayMongo's GCash checkout.
 */
function PaymentsPanel({ addendum, args }: { addendum: Addendum; args: Args }) {
    const [paying, setPaying] = useState<number | null>(null);

    const pay = (payment: AddendumPayment) => {
        setPaying(payment.id);
        router.post(
            paymentCheckout.url({ ...args, payment: payment.id }),
            {},
            {
                preserveScroll: true,
                onError: (errors) => {
                    const message = Object.values(errors)[0];

                    if (message) {
                        toast.error(message);
                    }
                },
                onFinish: () => setPaying(null),
            },
        );
    };

    const waitingOn = (payment: AddendumPayment): string | null => {
        if (payment.status === 'paid' || payment.isPayable) {
            return null;
        }

        if (payment.milestone === 2) {
            return addendum.payments[0]?.status !== 'paid'
                ? 'Due after the down payment clears and the extended work is handed in.'
                : `Due once the student hands in every extended task (${addendum.work.handedInCount} of ${addendum.work.taskCount} so far).`;
        }

        return null;
    };

    return (
        <Panel gap="md" style={{ padding: '18px 24px' }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    flexWrap: 'wrap',
                }}
            >
                <PanelKicker>Milestone payments (PayMongo · GCash)</PanelKicker>
                <Btn asChild variant="secondary" style={{ marginLeft: 'auto' }}>
                    <Link href={addendumRecords.url(args)}>
                        <ReceiptIcon />
                        Transaction records
                    </Link>
                </Btn>
            </div>

            {addendum.gateway === 'simulated' && (
                <div style={{ fontSize: 12, color: MUTED(65) }}>
                    Test mode: no PayMongo account is connected yet, so paying
                    opens SDPC’s test checkout and nothing is charged.
                </div>
            )}

            {addendum.payments.map((payment) => (
                <div
                    key={payment.id}
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 12,
                        flexWrap: 'wrap',
                        padding: '12px 0',
                        borderTop: '1px solid var(--color-divider)',
                    }}
                >
                    <div style={{ marginRight: 'auto', minWidth: 0 }}>
                        <div style={{ fontSize: 14 }}>
                            {payment.label} · {payment.percentage}%
                        </div>
                        <div style={{ fontSize: 12, color: MUTED(65) }}>
                            {payment.invoiceNumber}
                            {payment.providerPaymentId
                                ? ` · ${payment.providerPaymentId}`
                                : ''}
                            {payment.paidAt ? ` · paid ${payment.paidAt}` : ''}
                        </div>
                        {waitingOn(payment) && (
                            <div
                                style={{
                                    fontSize: 12,
                                    color: MUTED(65),
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 6,
                                }}
                            >
                                <LockSimpleIcon />
                                {waitingOn(payment)}
                            </div>
                        )}
                    </div>
                    <div style={{ fontSize: 15, fontWeight: 600 }}>
                        {payment.amountLabel}
                    </div>
                    <Tag
                        variant={
                            payment.status === 'paid' ? 'accent' : 'outline'
                        }
                    >
                        {payment.statusLabel}
                    </Tag>
                    {addendum.viewer.canPay && payment.isPayable && (
                        <Btn
                            variant="primary"
                            disabled={paying !== null}
                            onClick={() => pay(payment)}
                        >
                            {paying === payment.id ? (
                                <Spinner />
                            ) : (
                                <WalletIcon />
                            )}
                            Pay with GCash
                        </Btn>
                    )}
                </div>
            ))}

            {addendum.status === 'completed' && (
                <div style={{ fontSize: 12.5 }}>
                    Fully paid
                    {addendum.completedOn ? ` on ${addendum.completedOn}` : ''}.
                    The extension’s files and links are unlocked for the client
                    in Project Management.
                </div>
            )}
        </Panel>
    );
}
