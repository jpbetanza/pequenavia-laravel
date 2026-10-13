import { Breadcrumbs } from '@/components/breadcrumbs';
import { AppShell } from '@/components/app-shell';
import { BarraSuperior } from '@/components/menu-superior/barra-superior';
import { PainelDoAssistente } from '@/components/assistente/painel-do-assistente';
import { ProvedorDoAssistente } from '@/hooks/use-assistente';
import type { AppLayoutProps } from '@/types';

export default function AppHeaderLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <ProvedorDoAssistente>
            <AppShell variant="header">
                <BarraSuperior />
                {/* Corta o painel do assistente enquanto ele desliza para dentro ou para fora. */}
                <div className="flex min-h-0 flex-1 overflow-x-clip">
                    <main className="min-w-0 flex-1 overflow-x-clip">
                        {breadcrumbs.length > 1 && (
                            <div className="mx-auto max-w-[1376px] px-4 pt-4 md:px-8">
                                <Breadcrumbs breadcrumbs={breadcrumbs} />
                            </div>
                        )}
                        <div className="mx-auto w-full max-w-[1376px] md:px-4">
                            {children}
                        </div>
                    </main>
                    <PainelDoAssistente />
                </div>
            </AppShell>
        </ProvedorDoAssistente>
    );
}
