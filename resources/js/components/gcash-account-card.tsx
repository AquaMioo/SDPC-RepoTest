import { Form, router } from '@inertiajs/react';
import { WalletIcon } from '@phosphor-icons/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Input } from '@/components/sdpc/input';
import { Tag } from '@/components/sdpc/tag';
import { Spinner } from '@/components/ui/spinner';
import { destroy as gcashDestroy, update as gcashUpdate } from '@/routes/gcash';

export type GcashAccount = {
    /** Always masked (09******297); null while none is registered. */
    masked: string | null;
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Settings → GCash account. Clients and students only.
 *
 * Registered once; every Project Extension Addendum the account signs imports
 * it, and neither side can sign one until both have registered. Private: it
 * is on no profile, and even here it only ever comes back masked — the full
 * number never reaches the browser after it is saved.
 */
export default function GcashAccountCard({
    account,
}: {
    account: GcashAccount | null;
}) {
    const [replacing, setReplacing] = useState(false);
    const [removing, setRemoving] = useState(false);

    if (account === null) {
        return null;
    }

    const isRegistered = account.masked !== null;
    const showForm = !isRegistered || replacing;

    const remove = () => {
        setRemoving(true);
        router.delete(gcashDestroy.url(), {
            preserveScroll: true,
            onFinish: () => setRemoving(false),
        });
    };

    return (
        <div
            className="card elev-sm"
            style={{ marginTop: 24, padding: 20, gap: 14 }}
            data-test="gcash-account-card"
        >
            <h6 style={{ margin: 0 }}>GCash account</h6>

            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    flexWrap: 'wrap',
                    gap: 10,
                }}
            >
                <div style={{ marginRight: 'auto' }}>
                    <div
                        style={{
                            fontSize: 13.5,
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                        }}
                        data-test="gcash-masked"
                    >
                        <WalletIcon />
                        {account.masked ?? 'Not registered'}
                    </div>
                    <div style={{ fontSize: 12, color: MUTED(58) }}>
                        Used for project extension addenda: it is imported into
                        the addendum, and both sides need one before signing.
                        Only you can see it here, and only masked.
                    </div>
                </div>
                <Tag variant={isRegistered ? 'accent' : 'outline'}>
                    {isRegistered ? 'Registered' : 'Not registered'}
                </Tag>
            </div>

            {isRegistered && !replacing && (
                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                    <Btn variant="secondary" onClick={() => setReplacing(true)}>
                        Replace number
                    </Btn>
                    <Btn variant="ghost" disabled={removing} onClick={remove}>
                        {removing && <Spinner />}
                        Remove
                    </Btn>
                </div>
            )}

            {showForm && (
                <Form
                    {...gcashUpdate.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => setReplacing(false)}
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        gap: 10,
                        flexWrap: 'wrap',
                    }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div
                                className="field"
                                style={{
                                    flex: 1,
                                    minWidth: 220,
                                    maxWidth: 320,
                                }}
                            >
                                <label htmlFor="gcash-number">
                                    GCash mobile number
                                </label>
                                <Input
                                    id="gcash-number"
                                    name="gcash_number"
                                    inputMode="tel"
                                    autoComplete="off"
                                    maxLength={16}
                                    placeholder="09XX XXX XXXX"
                                    aria-invalid={Boolean(errors.gcash_number)}
                                />
                                <InputError
                                    message={errors.gcash_number}
                                    className="mt-1 text-[11px]"
                                />
                            </div>
                            <Btn
                                type="submit"
                                variant="primary"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {isRegistered ? 'Save new number' : 'Register'}
                            </Btn>
                            {replacing && (
                                <Btn
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setReplacing(false)}
                                >
                                    Cancel
                                </Btn>
                            )}
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}
