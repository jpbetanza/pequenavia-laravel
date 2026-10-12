import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, FileText, House } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { BotaoDoAssistente } from '@/components/assistente/botao-do-assistente';
import { MenuDaConta } from '@/components/menu-superior/menu-da-conta';
import {
    classeDaAba,
    MenuDoModulo,
} from '@/components/menu-superior/menu-do-modulo';
import { abaAtiva, secoesDoMenu } from '@/lib/menu/secoes';
import { calendario } from '@/routes/agendamentos';
import { inicio } from '@/routes';

export function BarraSuperior() {
    const { agenda, assistente } = usePage().props;
    const { url } = usePage();
    const secoes = secoesDoMenu(agenda?.solicitacoes_pendentes ?? 0);
    const ativa = abaAtiva(url, secoes);
    const [secaoAgendamentos, secaoCertificados] = secoes;

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
                    className="hidden h-full items-center md:flex"
                >
                    <Link
                        href={inicio()}
                        prefetch
                        aria-current={ativa === 'inicio' ? 'page' : undefined}
                        className={classeDaAba(ativa === 'inicio')}
                    >
                        <House aria-hidden="true" className="size-[18px]" />
                        Início
                    </Link>
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
                </nav>

                <div className="ml-auto flex items-center gap-3">
                    {assistente?.disponivel && <BotaoDoAssistente />}
                    <MenuDaConta />
                </div>
            </div>
        </header>
    );
}
