import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, FileText, House, Menu, Sparkles, X } from 'lucide-react';
import { useState } from 'react';
import AppLogo from '@/components/app-logo';
import { BotaoDoAssistente } from '@/components/assistente/botao-do-assistente';
import { Button } from '@/components/ui/button';
import { MenuDaConta } from '@/components/menu-superior/menu-da-conta';
import { MenuDoCelular } from '@/components/menu-superior/menu-do-celular';
import {
    classeDaAba,
    MenuDoModulo,
} from '@/components/menu-superior/menu-do-modulo';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
} from '@/components/ui/navigation-menu';
import { useAssistente } from '@/hooks/use-assistente';
import { abaAtiva, secoesDoMenu } from '@/lib/menu/secoes';
import { calendario } from '@/routes/agendamentos';
import { inicio } from '@/routes';

export function BarraSuperior() {
    const { agenda, assistente } = usePage().props;
    const { url } = usePage();
    const secoes = secoesDoMenu(agenda?.solicitacoes_pendentes ?? 0);
    const ativa = abaAtiva(url, secoes);
    const [secaoAgendamentos, secaoCertificados] = secoes;
    const [menuAberto, setMenuAberto] = useState(false);
    const { alternar } = useAssistente();
    const assistenteDisponivel = Boolean(assistente?.disponivel);

    return (
        <header className="sticky top-0 z-40 h-16 border-b border-border bg-background print:hidden">
            <div className="mx-auto flex h-full max-w-[1376px] items-center gap-7 px-4 md:px-8">
                <Link
                    href={inicio()}
                    prefetch
                    className="flex shrink-0 items-center gap-2.5"
                >
                    <AppLogo />
                </Link>

                <nav
                    aria-label="Navegação do app"
                    className="hidden h-full items-center lg:flex"
                >
                    <NavigationMenu
                        viewport={false}
                        delayDuration={100}
                        aria-label="Módulos"
                        className="h-full max-w-none flex-none justify-start"
                    >
                        <NavigationMenuList className="h-full flex-none justify-start gap-0">
                            <NavigationMenuItem>
                                <NavigationMenuLink asChild>
                                    <Link
                                        href={inicio()}
                                        prefetch
                                        aria-current={
                                            ativa === 'inicio'
                                                ? 'page'
                                                : undefined
                                        }
                                        className={classeDaAba(
                                            ativa === 'inicio',
                                        )}
                                    >
                                        <House
                                            aria-hidden="true"
                                            className="size-[18px]"
                                        />
                                        Início
                                    </Link>
                                </NavigationMenuLink>
                            </NavigationMenuItem>
                            <MenuDoModulo
                                secao={secaoAgendamentos}
                                ativa={ativa === secaoAgendamentos.id}
                                icone={CalendarDays}
                                acaoFinal={{
                                    titulo: 'Nova reserva',
                                    href: calendario(),
                                }}
                            />
                            <MenuDoModulo
                                secao={secaoCertificados}
                                ativa={ativa === secaoCertificados.id}
                                icone={FileText}
                            />
                        </NavigationMenuList>
                    </NavigationMenu>
                </nav>

                <div className="ml-auto flex items-center gap-3">
                    {assistenteDisponivel && (
                        <>
                            <BotaoDoAssistente />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Abrir o assistente"
                                aria-controls="painel-assistente"
                                onClick={alternar}
                                className="size-11 rounded-full lg:hidden"
                            >
                                <Sparkles
                                    aria-hidden="true"
                                    className="size-4"
                                />
                            </Button>
                        </>
                    )}
                    <div className="hidden lg:block">
                        <MenuDaConta />
                    </div>
                    <Button
                        type="button"
                        variant="default"
                        size="icon"
                        aria-label={menuAberto ? 'Fechar menu' : 'Abrir menu'}
                        aria-expanded={menuAberto}
                        aria-controls="menu-do-celular"
                        onClick={() => setMenuAberto((aberto) => !aberto)}
                        className="size-11 rounded-xl lg:hidden"
                    >
                        {menuAberto ? (
                            <X aria-hidden="true" className="size-[22px]" />
                        ) : (
                            <Menu aria-hidden="true" className="size-[22px]" />
                        )}
                    </Button>
                </div>
            </div>

            <MenuDoCelular
                aberto={menuAberto}
                onAbertoChange={setMenuAberto}
                secoes={secoes}
                ativa={ativa}
                assistenteDisponivel={assistenteDisponivel}
            />
        </header>
    );
}
