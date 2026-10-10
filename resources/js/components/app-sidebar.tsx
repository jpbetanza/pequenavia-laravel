import { Link } from '@inertiajs/react';
import { FileText, History, Settings } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as historico } from '@/routes/historico';
import { index as meusCertificados } from '@/routes/meus-certificados';
import { edit as editarPerfil } from '@/routes/profile';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Modelos',
        href: meusCertificados(),
        icon: FileText,
    },
    {
        title: 'Histórico',
        href: historico(),
        icon: History,
    },
];

export function AppSidebar() {
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
                            <Link href={meusCertificados()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
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
