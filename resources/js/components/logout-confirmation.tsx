import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { logout } from '@/routes';

const CONFIRM_LOGOUT_EVENT = 'sdpc:confirm-logout';

/**
 * Ask before signing out, from anywhere.
 *
 * Every "Log out" control calls this instead of posting to logout directly.
 * One dialog serves all of them: the admin header, the account menu, the
 * settings sidebar and the auth screens. Several of those unmount as soon as
 * they are used (a dropdown closes on select), so a dialog living inside them
 * would vanish with the menu.
 */
export function confirmLogout(): void {
    window.dispatchEvent(new Event(CONFIRM_LOGOUT_EVENT));
}

/**
 * The one "Confirm Logout" dialog, mounted once at the app root.
 *
 * Radix's Dialog keeps keyboard focus inside while it is open and closes on
 * Escape. Cancel leaves the session exactly as it was; Log out posts to
 * Fortify's logout, which ends the session, rotates the token and sends the
 * browser to the landing page.
 */
export default function LogoutConfirmation() {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        const show = () => setOpen(true);

        window.addEventListener(CONFIRM_LOGOUT_EVENT, show);

        return () => window.removeEventListener(CONFIRM_LOGOUT_EVENT, show);
    }, []);

    const logOut = () => {
        /* Nothing cached for this account may be shown to the next one. */
        router.flushAll();

        router.post(
            logout.url(),
            {},
            {
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setOpen(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent data-test="logout-confirmation">
                <DialogHeader>
                    <DialogTitle>Confirm Logout</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to log out of your account?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-3">
                    <DialogClose asChild>
                        <Button variant="secondary" disabled={processing}>
                            Cancel
                        </Button>
                    </DialogClose>

                    <Button
                        data-test="logout-confirm"
                        disabled={processing}
                        onClick={logOut}
                    >
                        Log out
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
