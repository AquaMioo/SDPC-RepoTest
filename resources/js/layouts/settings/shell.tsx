import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AdminLayout from '@/layouts/admin-layout';
import ClientLayout from '@/layouts/client/client-layout';

type SharedProps = {
    auth?: { user?: { role?: string } | null; role?: string | null };
};

/**
 * Picks the chrome the settings screens sit inside.
 *
 * Settings is the one area both portals share, so it cannot hard-code either
 * shell. Administrators get the admin chrome; everyone else gets the user app's.
 *
 * The choice is made on role rather than on the presence of a team, because
 * ClientLayout calls useCurrentTeam(), which throws when there is none — and an
 * administrator has no team at all.
 */
export default function SettingsShell({ children }: { children: ReactNode }) {
    const page = usePage<SharedProps>();
    const role = page.props.auth?.role ?? page.props.auth?.user?.role;

    const Shell = role === 'admin' ? AdminLayout : ClientLayout;

    return <Shell>{children}</Shell>;
}
