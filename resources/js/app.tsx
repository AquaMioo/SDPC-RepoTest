import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import NavigationSkeleton from '@/components/sdpc/navigation-skeleton';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import ClientLayout from '@/layouts/client/client-layout';
import SettingsLayout from '@/layouts/settings/layout';
import SettingsShell from '@/layouts/settings/shell';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            /*
             * The legal documents are public and bring PublicLayout with them,
             * the same way the landing page does. Without this case they fall
             * through to AppLayout at the bottom, whose chrome reads the
             * signed-in user and dies on `avatar` for exactly the signed-out
             * visitor these pages exist to serve — a blank white page, and no
             * server-side test can see it.
             */
            case name === 'legal':
                return null;
            // The admin sign in screen is a standalone centred card, so it
            // brings its own shell rather than the portal chrome.
            case name === 'admin/login':
                return null;
            case name.startsWith('admin/'):
                return AdminLayout;
            // Log in carries the pitch beside the form, so it brings its own
            // split shell rather than the centred auth column. Every other
            // auth screen keeps AuthLayout.
            case name === 'auth/login':
            // Register brings the same split shell, and its pitch swaps sides
            // with the role picker — AuthLayout's centred column cannot hold
            // either half of that.
            case name === 'auth/register':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            /*
             * Client, student and the ground they share — messaging, the
             * contract and the ledger — all wear the same shell; it picks its
             * nav from the signed-in role.
             *
             */
            case name.startsWith('client/'):
            case name.startsWith('student/'):
            case name.startsWith('messaging/'):
            case name.startsWith('agreements/'):
            case name.startsWith('notifications/'):
            // Shared by both sides of a signed agreement, like the contract itself.
            case name.startsWith('project-management/'):
            case name.startsWith('billing/'):
                return ClientLayout;
            /*
             * Teams is reached from the header beside Agreement now, so it
             * drops SettingsLayout's rail — that sidebar no longer has a Teams
             * row to highlight — but keeps SettingsShell.
             *
             * The shell is not optional here. settings/teams carries no role
             * middleware, so an administrator can open it, and ClientLayout
             * calls useCurrentTeam(), which throws for an account with no team
             * — which is every administrator. SettingsShell is what picks the
             * admin chrome instead.
             */
            case name.startsWith('teams/'):
                return SettingsShell;
            // Settings is shared by both portals, so the chrome is picked from
            // the signed-in role rather than fixed here.
            case name.startsWith('settings/'):
                return [SettingsShell, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <NavigationSkeleton />
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
