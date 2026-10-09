import { Head, Link, router } from '@inertiajs/react';
import { FlaskIcon, WalletIcon } from '@phosphor-icons/react';
import { useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { Panel } from '@/components/sdpc/panel';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    payment: {
        label: string;
        amountLabel: string;
        invoiceNumber: string;
        reference: string;
        projectTitle: string;
        confirmUrl: string;
        cancelUrl: string;
    };
};

/**
 * SDPC's test checkout, standing in for PayMongo's hosted GCash page while no
 * PayMongo key is configured. It says so: nothing is charged and no wallet is
 * asked for. Confirming clears the milestone the way PayMongo's webhook would.
 */
export default function SimulatedCheckout({ payment }: Props) {
    const [busy, setBusy] = useState(false);

    const confirm = () => {
        setBusy(true);
        router.post(payment.confirmUrl, {}, { onFinish: () => setBusy(false) });
    };

    return (
        <>
            <Head title="Test checkout" />

            <div
                style={{
                    minHeight: '100vh',
                    display: 'grid',
                    placeItems: 'center',
                    padding: '24px 16px',
                    background: 'var(--color-bg)',
                }}
            >
                <Panel
                    padding="lg"
                    gap="lg"
                    style={{ width: '100%', maxWidth: 440, padding: 28 }}
                >
                    <div
                        role="status"
                        style={{
                            display: 'flex',
                            gap: 8,
                            alignItems: 'flex-start',
                            padding: '10px 12px',
                            borderRadius: 8,
                            background: '#fff4e5',
                            color: '#7a4a00',
                            fontSize: 12.5,
                            lineHeight: 1.5,
                        }}
                    >
                        <FlaskIcon style={{ flex: 'none', marginTop: 2 }} />
                        <span>
                            Test checkout. No PayMongo account is connected, so
                            nothing is charged and no GCash wallet is used.
                        </span>
                    </div>

                    <div>
                        <div style={{ fontSize: 12, opacity: 0.6 }}>
                            {payment.projectTitle} · {payment.reference}
                        </div>
                        <h4 style={{ margin: '4px 0 2px' }}>{payment.label}</h4>
                        <div style={{ fontSize: 12, opacity: 0.6 }}>
                            Invoice {payment.invoiceNumber}
                        </div>
                    </div>

                    <div style={{ fontSize: 28, fontWeight: 600 }}>
                        {payment.amountLabel}
                    </div>

                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <Btn
                            variant="primary"
                            disabled={busy}
                            onClick={confirm}
                        >
                            {busy ? <Spinner /> : <WalletIcon />}
                            Pay with GCash (test)
                        </Btn>
                        <Btn asChild variant="ghost">
                            <Link href={payment.cancelUrl}>Cancel</Link>
                        </Btn>
                    </div>
                </Panel>
            </div>
        </>
    );
}
