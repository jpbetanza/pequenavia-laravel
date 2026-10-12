import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, FileText, House, Settings } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { useCurrentUrl } from '@/hooks/use-current-url';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { calendario } from '@/routes/agendamentos';
import { index as equipamentos } from '@/routes/agendamentos/equipamentos';
import { index as espacos } from '@/routes/agendamentos/espacos';
import { index as grupos } from '@/routes/agendamentos/grupos';
import { index as solicitacoes } from '@/routes/agendamentos/solicitacoes';
import { index as historico } from '@/routes/historico';
import { index as meusCertificados } from '@/routes/meus-certificados';
import { inicio } from '@/routes';
import { edit as editarPerfil } from '@/routes/profile';
import type { NavSecao } from '@/types';

function secoesDoMenu(solicitacoesPendentes: number): NavSecao[] {
    return [
        {
            id: 'agendamentos',
            title: 'Agendamentos',
            icon: CalendarDays,
            items: [
                { title: 'Calendário', href: calendario() },
                {
                    title: 'Solicitações',
                    href: solicitacoes(),
                    contador: solicitacoesPendentes,
                },
                { title: 'Espaços', href: espacos() },
                { title: 'Equipamentos', href: equipamentos() },
                { title: 'Grupos e pastorais', href: grupos() },
            ],
        },
        {
            id: 'certificados',
            title: 'Certificados',
            icon: FileText,
            items: [
                { title: 'Modelos', href: meusCertificados() },
                { title: 'Histórico', href: historico() },
            ],
        },
    ];
}

export function AppSidebar() {
    const { agenda } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            className="h-auto px-2 py-1"
                            asChild
                        >
                            <Link href={inicio()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarMenu className="gap-1.5">
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(inicio())}
                            tooltip={{ children: 'Início' }}
                            className="h-11 gap-3 px-3 text-[15px] font-medium"
                        >
                            <Link href={inicio()} prefetch>
                                <House />
                                <span>Início</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavMain
                    secoes={secoesDoMenu(agenda?.solicitacoes_pendentes ?? 0)}
                />
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: 'Configurações' }}
                            className="h-11 gap-3 px-3 text-[15px] font-medium"
                        >
                            <Link href={editarPerfil()} prefetch>
                                <Settings />
                                <span>Configurações</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
