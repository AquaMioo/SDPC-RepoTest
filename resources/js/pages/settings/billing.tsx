import { Head } from '@inertiajs/react';

import GcashAccountCard from '@/components/gcash-account-card';
import type { GcashAccount } from '@/components/gcash-account-card';

/**
 * Settings → Billing & Payment (owner, 2026-10-20). Clients and students
 * only: the GCash account every Project Extension Addendum imports, moved
 * here from Account settings.
 */
export default function Billing({ gcash }: { gcash: GcashAccount }) {
    return (
        <>
            <Head title="Billing & Payment" />

            <h4 style={{ margin: '0 0 16px' }}>Billing &amp; Payment</h4>

            <GcashAccountCard account={gcash} />
        </>
    );
}
