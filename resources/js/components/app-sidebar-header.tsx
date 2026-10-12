import { usePage } from '@inertiajs/react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { BotaoDoAssistente } from '@/components/assistente/botao-do-assistente';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const temTrilha = breadcrumbs.length > 1;
    const { assistente } = usePage().props;

    return (
        <header
            className={cn(
                'flex shrink-0 items-center gap-2 px-4 pt-3 md:px-6',
                temTrilha &&
                    'h-16 border-b border-sidebar-border/50 group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4',
            )}
        >
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {assistente?.disponivel && <BotaoDoAssistente />}
        </header>
    );
}
