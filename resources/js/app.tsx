import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import ClientLayout from '@/layouts/client/client-layout';
import SettingsLayout from '@/layouts/settings/layout';
import SettingsShell from '@/layouts/settings/shell';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            // The admin sign in screen is a standalone centred card, so it
            // brings its own shell rather than the portal chrome.
            case name === 'admin/login':
                return null;
            case name.startsWith('admin/'):
                return AdminLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            // Client, student and the messaging they share all wear the same
            // shell; it picks its nav from the signed-in role.
            case name.startsWith('client/'):
            case name.startsWith('student/'):
            case name.startsWith('messaging/'):
                return ClientLayout;
            // Settings is shared by both portals, so the chrome is picked from
            // the signed-in role rather than fixed here.
            case name.startsWith('settings/'):
            case name.startsWith('teams/'):
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
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

