import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import LayoutPublico from '@/layouts/reservar/layout-publico';
import SettingsLayout from '@/layouts/settings/layout';
import { prepararInstalacao } from '@/lib/pwa/instalacao';

const appName = import.meta.env.VITE_APP_NAME || 'PequenaVia';

// Antes do React: o pedido de instalação do Chrome pode chegar logo no carregamento.
if (typeof window !== 'undefined') {
    prepararInstalacao();
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('reservar/'):
                return LayoutPublico;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
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
