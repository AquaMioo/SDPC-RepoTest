import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Input, Textarea } from '@/components/sdpc/input';
import { Panel, PanelDivider } from '@/components/sdpc/panel';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { store as changesStore } from '@/routes/agreements/changes';
import { store as signaturesStore } from '@/routes/agreements/signatures';
import type { Agreement } from '@/types/agreements';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Props = {
    agreement: Agreement;
    /** The design paints this panel accent-washed on the Contract screen. */
    tone?: 'plain' | 'accent';
    /** Buttons placed before the built-in actions, e.g. "Read full contract". */
    leading?: ReactNode;
};

/**
 * The design's "Agreement acknowledgement" panel — four statements, a typed
 * name and the two answers a party can give.
 *
 * Shared by the Agreement and Contract screens rather than written twice: they
 * put the same signature on the same document, and two copies of a signing
 * form is the fastest way to have one of them stop asking for something.
 */
export default function SignatureForm({
    agreement,
    tone = 'plain',
    leading,
}: Props) {
    const team = useCurrentTeam();
    const [isRequestingChanges, setIsRequestingChanges] = useState(false);

    const { viewer } = agreement;

    const signature = useForm<{
        signed_name: string;
        acknowledgements: string[];
    }>({ signed_name: '', acknowledgements: [] });

    const changes = useForm({ note: '' });

    const isSigning = viewer.canSign && !viewer.hasSigned;

    const toggle = (key: string) => {
        signature.setData(
            'acknowledgements',
            signature.data.acknowledgements.includes(key)
                ? signature.data.acknowledgements.filter((k) => k !== key)
                : [...signature.data.acknowledgements, key],
        );
    };

    const sign = () => {
        signature.post(
            signaturesStore.url({
                current_team: team.slug,
                agreement: agreement.id,
            }),
            { preserveScroll: true },
        );
    };

    const requestChanges = () => {
        changes.post(
            changesStore.url({
                current_team: team.slug,
                agreement: agreement.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <Panel
            padding="lg"
            gap="lg"
            style={{
                padding: '22px 24px',
                ...(tone === 'accent'
                    ? {
                          background:
                              'color-mix(in srgb, var(--color-accent) 8%, var(--color-surface))',
                      }
                    : {}),
            }}
        >
            <h6 style={{ margin: 0 }}>Agreement acknowledgement</h6>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                    gap: '14px 32px',
                }}
            >
                {agreement.acknowledgements.map((acknowledgement) => (
                    <label
                        key={acknowledgement.key}
                        style={{
                            display: 'flex',
                            gap: 11,
                            alignItems: 'flex-start',
                            fontSize: 12.5,
                            lineHeight: 1.55,
                            cursor: isSigning ? 'pointer' : 'default',
                            color: isSigning ? undefined : MUTED(70),
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
                            disabled={!isSigning}
                            checked={
                                viewer.hasSigned ||
                                signature.data.acknowledgements.includes(
                                    acknowledgement.key,
                                )
                            }
                            onChange={() => toggle(acknowledgement.key)}
                        />
                        {acknowledgement.label}
                    </label>
                ))}
            </div>

            <InputError
                message={signature.errors.acknowledgements}
                className="text-[11px]"
            />

            <PanelDivider />

            {isSigning && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        gap: 12,
                        flexWrap: 'wrap',
                    }}
                >
                    <div className="field" style={{ maxWidth: 320, flex: 1 }}>
                        <label htmlFor="signed-name">
                            Type your full name to sign
                        </label>
                        <Input
                            id="signed-name"
                            value={signature.data.signed_name}
                            maxLength={120}
                            autoComplete="off"
                            placeholder="Your name, as you would write it"
                            aria-invalid={Boolean(signature.errors.signed_name)}
                            onChange={(event) =>
                                signature.setData(
                                    'signed_name',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError
                            message={signature.errors.signed_name}
                            className="mt-1 text-[11px]"
                        />
                    </div>
                </div>
            )}

            {isRequestingChanges && (
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 8,
                    }}
                >
                    <label
                        htmlFor="change-note"
                        style={{ fontSize: 12, color: MUTED(70) }}
                    >
                        What needs to move? The other party sees this verbatim.
                    </label>
                    <Textarea
                        id="change-note"
                        value={changes.data.note}
                        maxLength={2000}
                        placeholder="The turnover milestone lands during finals week — could it move to the following Monday?"
                        aria-invalid={Boolean(changes.errors.note)}
                        onChange={(event) =>
                            changes.setData('note', event.target.value)
                        }
                    />
                    <InputError
                        message={changes.errors.note}
                        className="text-[11px]"
                    />
                    <div style={{ display: 'flex', gap: 10 }}>
                        <Btn
                            variant="primary"
                            disabled={changes.processing}
                            onClick={requestChanges}
                        >
                            {changes.processing ? 'Sending…' : 'Send request'}
                        </Btn>
                        <Btn
                            variant="ghost"
                            onClick={() => setIsRequestingChanges(false)}
                        >
                            Cancel
                        </Btn>
                    </div>
                </div>
            )}

            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    flexWrap: 'wrap',
                }}
            >
                <span
                    style={{
                        fontSize: 11.5,
                        color: MUTED(68),
                        marginRight: 'auto',
                    }}
                >
                    {viewer.hasSigned
                        ? 'You have signed. Your name, account ID and timestamp are on the contract log.'
                        : 'Signing records your name, account ID and timestamp to the contract log.'}
                </span>

                {leading}

                {viewer.canRequestChanges && !isRequestingChanges && (
                    <Btn onClick={() => setIsRequestingChanges(true)}>
                        Request changes
                    </Btn>
                )}

                {isSigning && (
                    <Btn
                        variant="primary"
                        disabled={signature.processing}
                        onClick={sign}
                    >
                        {signature.processing ? 'Signing…' : 'Sign agreement'}
                    </Btn>
                )}
            </div>
        </Panel>
    );
}
