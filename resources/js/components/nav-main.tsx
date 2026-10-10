import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { NavSecao } from '@/types';

const CLASSE_DO_BOTAO = 'h-11 gap-3 px-3 text-[15px] font-medium';

function chaveDaSecao(id: string): string {
    return `menu-secao-${id}`;
}

/** Estado lembrado da seção: true/false, ou null quando nunca foi guardado. */
function lerSecaoAberta(id: string): boolean | null {
    try {
        const valor = window.localStorage.getItem(chaveDaSecao(id));

        return valor === null ? null : valor === 'true';
    } catch {
        return null;
    }
}

function guardarSecaoAberta(id: string, aberta: boolean): void {
    try {
        window.localStorage.setItem(chaveDaSecao(id), String(aberta));
    } catch {
        // Sem localStorage (aba anônima, bloqueio): a seção só não é lembrada.
    }
}

function SecaoDoMenu({ secao }: { secao: NavSecao }) {
    const { isCurrentUrl } = useCurrentUrl();
    const { state, isMobile } = useSidebar();
    const contemRotaAtual = secao.items.some((item) => isCurrentUrl(item.href));
    const [aberta, setAberta] = useState(
        () => contemRotaAtual || (lerSecaoAberta(secao.id) ?? true),
    );

    const alternar = (valor: boolean) => {
        setAberta(valor);
        guardarSecaoAberta(secao.id, valor);
    };

    // Barra recolhida: a seção vira um atalho para o primeiro subitem.
    if (state === 'collapsed' && !isMobile) {
        return (
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={contemRotaAtual}
                    tooltip={{ children: secao.title }}
                    className={CLASSE_DO_BOTAO}
                >
                    <Link href={secao.items[0].href} prefetch>
                        <secao.icon />
                        <span>{secao.title}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    }

    return (
        <Collapsible
            asChild
            open={aberta}
            onOpenChange={alternar}
            className="group/secao"
        >
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        tooltip={{ children: secao.title }}
                        className={cn(CLASSE_DO_BOTAO, 'font-semibold')}
                    >
                        <secao.icon />
                        <span className="flex-1">{secao.title}</span>
                        <ChevronDown
                            aria-hidden="true"
                            className="transition-transform group-data-[state=closed]/secao:-rotate-90"
                        />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub className="mt-1 mb-1.5 ml-5.5 pr-0">
                        {secao.items.map((item) => {
                            const ativo = isCurrentUrl(item.href);
                            const contador = item.contador ?? 0;

                            return (
                                <SidebarMenuSubItem key={item.title}>
                                    <SidebarMenuSubButton
                                        asChild
                                        isActive={ativo}
                                        className={cn(
                                            'h-11 px-3 text-[14px]',
                                            ativo &&
                                                'bg-card font-semibold text-primary shadow-xs',
                                        )}
                                    >
                                        <Link
                                            href={item.href}
                                            prefetch
                                            aria-current={
                                                ativo ? 'page' : undefined
                                            }
                                        >
                                            <span>{item.title}</span>
                                            {contador > 0 && (
                                                <SidebarMenuBadge
                                                    aria-label={`${contador} pendentes`}
                                                    className="top-2.5 right-2 rounded-full bg-primary text-primary-foreground"
                                                >
                                                    {contador}
                                                </SidebarMenuBadge>
                                            )}
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            );
                        })}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

export function NavMain({ secoes }: { secoes: NavSecao[] }) {
    return (
        <SidebarMenu className="gap-1.5">
            {secoes.map((secao) => (
                <SecaoDoMenu key={secao.id} secao={secao} />
            ))}
        </SidebarMenu>
    );
}
