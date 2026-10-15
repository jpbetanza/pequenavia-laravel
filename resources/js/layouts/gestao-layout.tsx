import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ChartColumn,
    Lightbulb,
    type LucideIcon,
    MessageSquareText,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Switch } from '@/components/ui/switch';
import { formatarInstante } from '@/lib/gestao/formatar';
import { cn } from '@/lib/utils';
import { escopo, index as visaoGeral } from '@/routes/gestao';
import { index as clientes } from '@/routes/gestao/clientes';
import { index as conversas } from '@/routes/gestao/conversas';
import { index as temas } from '@/routes/gestao/temas';
import { inicio } from '@/routes';

type Item = {
    titulo: string;
    href: string;
    icone: LucideIcon;
    /** Caminho (sem query) em que o item fica marcado. */
    ativoEm: (caminho: string) => boolean;
    contador?: number;
};

function caminhoDe(url: string): string {
    return url.split('?')[0].split('#')[0];
}

export default function GestaoLayout({ children }: { children: ReactNode }) {
    const { url, props } = usePage();
    const caminho = caminhoDe(url);
    const gestao = props.gestao;
    const incluirGestores = gestao?.incluir_gestores ?? false;

    const alternarGestores = (incluir: boolean) => {
        router.put(
            escopo.url(),
            { incluir_gestores: incluir },
            { preserveScroll: true },
        );
    };

    const itens: Item[] = [
        {
            titulo: 'Visão geral',
            href: visaoGeral.url(),
            icone: ChartColumn,
            ativoEm: (atual) => atual === '/gestao',
        },
        {
            titulo: 'Conversas e casos de uso',
            href: conversas.url(),
            icone: MessageSquareText,
            ativoEm: (atual) => atual.startsWith('/gestao/conversas'),
        },
        {
            titulo: 'Pedidos não atendidos',
            href: temas.url(),
            icone: Lightbulb,
            ativoEm: (atual) => atual.startsWith('/gestao/temas'),
            contador: gestao?.temas_novos ?? 0,
        },
        {
            titulo: 'Clientes',
            href: clientes.url(),
            icone: Users,
            ativoEm: (atual) => atual.startsWith('/gestao/clientes'),
        },
    ];

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground md:flex-row">
            <nav
                aria-label="Gestão"
                className="bg-[#221A17] px-4 py-3 text-[#D8CEC2] md:w-58 md:shrink-0 md:px-3.5 md:py-5.5"
            >
                <div className="flex flex-col gap-3 md:sticky md:top-5.5 md:max-h-[calc(100svh-2.75rem)] md:min-h-[calc(100svh-2.75rem)] md:gap-6 md:overflow-y-auto">
                    <div className="flex items-center gap-2.5 px-2">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#F5F0E6]">
                            <AppLogoIcon className="size-6 object-contain" />
                        </span>
                        <span className="flex flex-col leading-tight">
                            <span className="font-serif text-[21px] font-bold text-[#F5F0E6]">
                                {props.name}
                            </span>
                            <span className="text-[11px] font-semibold tracking-[0.14em] text-dourado-claro uppercase">
                                Gestão
                            </span>
                        </span>
                    </div>

                    <ul className="relative -mx-4 flex gap-1 overflow-x-auto px-4 md:mx-0 md:flex-col md:gap-0.5 md:overflow-visible md:px-0">
                        {itens.map((item) => {
                            const Icone = item.icone;
                            const ativo = item.ativoEm(caminho);

                            return (
                                <li
                                    key={item.href}
                                    className="shrink-0 md:shrink"
                                >
                                    <Link
                                        href={item.href}
                                        aria-current={
                                            ativo ? 'page' : undefined
                                        }
                                        className={cn(
                                            'flex min-h-11 items-center gap-2.5 rounded-[10px] px-2.5 text-sm whitespace-nowrap transition-colors outline-none focus-visible:ring-2 focus-visible:ring-dourado-claro',
                                            ativo
                                                ? 'bg-[rgba(217,185,122,0.16)] font-semibold text-[#F5F0E6]'
                                                : 'hover:bg-[rgba(245,240,230,0.08)] hover:text-[#F5F0E6]',
                                        )}
                                    >
                                        <Icone
                                            aria-hidden
                                            className="size-[18px] shrink-0"
                                        />
                                        <span className="min-w-0 flex-1 md:whitespace-normal">
                                            {item.titulo}
                                        </span>
                                        {item.contador !== undefined &&
                                            item.contador > 0 && (
                                                <span className="shrink-0 rounded-full bg-dourado-claro px-1.5 text-xs font-semibold text-[#221A17]">
                                                    {item.contador}
                                                    <span className="sr-only">
                                                        {' '}
                                                        novos
                                                    </span>
                                                </span>
                                            )}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>

                    {gestao && (
                        <label className="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-[10px] px-2.5 text-sm md:mt-auto">
                            <Switch
                                checked={incluirGestores}
                                onCheckedChange={alternarGestores}
                                className="data-[state=checked]:bg-dourado-claro data-[state=unchecked]:bg-[rgba(245,240,230,0.2)]"
                            />
                            <span className="flex flex-col leading-tight">
                                <span className="text-[#F5F0E6]">
                                    Incluir minhas conversas
                                </span>
                                <span className="text-xs">
                                    Contas de gestão nos números
                                </span>
                            </span>
                        </label>
                    )}

                    <Link
                        href={inicio()}
                        className="flex min-h-11 items-center gap-2.5 rounded-[10px] px-2.5 text-sm whitespace-nowrap outline-none hover:bg-[rgba(245,240,230,0.08)] focus-visible:ring-2 focus-visible:ring-dourado-claro"
                    >
                        <ArrowLeft
                            aria-hidden
                            className="size-[18px] shrink-0"
                        />
                        Voltar ao app
                    </Link>

                    {gestao && (
                        <div className="hidden flex-col gap-1 rounded-xl bg-[rgba(245,240,230,0.06)] p-3 text-xs md:flex">
                            <span className="flex items-center gap-1.5 font-semibold text-[#F5F0E6]">
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'size-[7px] rounded-full',
                                        gestao.classificacao.ultima
                                            ? 'bg-[#7FB89A]'
                                            : 'bg-dourado-claro',
                                    )}
                                />
                                Classificação
                            </span>
                            {gestao.classificacao.ultima ? (
                                <span>
                                    Última rodada em{' '}
                                    {formatarInstante(
                                        gestao.classificacao.ultima.iniciada_em,
                                    )}
                                    :{' '}
                                    {
                                        gestao.classificacao.ultima
                                            .conversas_lidas
                                    }{' '}
                                    conversas lidas e{' '}
                                    {gestao.classificacao.ultima.resumidas}{' '}
                                    resumidas.
                                </span>
                            ) : (
                                <span>Ainda não houve uma rodada.</span>
                            )}
                            <span className="text-dourado-claro">
                                Próxima às {gestao.classificacao.proxima}
                            </span>
                        </div>
                    )}
                </div>
            </nav>

            <main className="min-w-0 flex-1 p-4 sm:p-6 md:px-8 md:py-7">
                {incluirGestores && (
                    <p className="mb-4 rounded-[10px] border border-dourado-claro/50 bg-dourado-claro/10 px-3 py-2 text-sm">
                        Os números e as listas incluem as contas de gestão.{' '}
                        <button
                            type="button"
                            onClick={() => alternarGestores(false)}
                            className="font-semibold underline underline-offset-2"
                        >
                            Mostrar só clientes
                        </button>
                    </p>
                )}
                {children}
            </main>
        </div>
    );
}
