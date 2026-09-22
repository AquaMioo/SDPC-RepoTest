import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DashboardInvitation } from '@/types';

/**
 * Decides when the pending team invitations dialog is open.
 *
 * It opens by itself while an invitation is waiting, and closing it has to
 * stick — so what is remembered is the dismissal, not the openness. The page
 * address is the key: the bell links a "you were invited" row to the dashboard
 * with `?invitation=`, and on the dashboard itself that is a re-render of a
 * component that is already mounted, where state set once at mount would never
 * run again and the row would do nothing.
 */
export function usePendingInvitations(
    invitations: DashboardInvitation[],
): [boolean, (open: boolean) => void] {
    const { url } = usePage();
    const [dismissedOn, setDismissedOn] = useState<string | null>(null);

    const open = invitations.length > 0 && dismissedOn !== url;

    const setOpen = (next: boolean) => setDismissedOn(next ? null : url);

    return [open, setOpen];
}
