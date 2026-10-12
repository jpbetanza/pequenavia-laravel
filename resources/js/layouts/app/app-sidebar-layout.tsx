import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { PainelDoAssistente } from '@/components/assistente/painel-do-assistente';
import { ProvedorDoAssistente } from '@/hooks/use-assistente';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <ProvedorDoAssistente>
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent
                    variant="sidebar"
                    className="min-w-0 overflow-x-clip"
                >
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    <div className="flex min-h-0 flex-1">
                        <div className="min-w-0 flex-1">{children}</div>
                        <PainelDoAssistente />
                    </div>
                </AppContent>
            </AppShell>
        </ProvedorDoAssistente>
    );
}
