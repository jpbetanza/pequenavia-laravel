import { Head, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, Printer } from 'lucide-react';
import { useEffect, useState } from 'react';
import AgendaCelular from '@/components/agenda/agenda-celular';
import FiltroDeEspacos from '@/components/agenda/filtro-de-espacos';
import GradeDaSemana from '@/components/agenda/grade-da-semana';
import MiniCalendario from '@/components/agenda/mini-calendario';
import OcupacaoDoDia from '@/components/agenda/ocupacao-do-dia';
import PainelDeReserva, {
    type AlvoDoPainel,
} from '@/components/agenda/painel-de-reserva';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import { Button } from '@/components/ui/button';
import {
    CHAVE_DO_FILTRO,
    lerFiltroSalvo,
    montarFiltroSalvo,
    reconciliarSelecao,
    type FiltroSalvo,
} from '@/lib/agenda/filtro';
import {
    lerPedidoDeNovaReserva,
    removerParametrosDeNovaReserva,
} from '@/lib/agenda/abrir-reserva';
import {
    dataPorExtenso,
    diasDaSemana,
    hojeDe,
    intervaloPorExtenso,
    somarDias,
} from '@/lib/agenda/datas';
import { cn } from '@/lib/utils';
import { calendario as rota } from '@/routes/agendamentos';
import type {
    PropsDoCalendario,
    ReservaDoCalendario,
} from '@/types/agendamentos';

const PASSO_DA_NAVEGACAO = { semana: 7, dia: 1 } as const;

export default function Calendario({
    semana,
    visao,
    data,
    reservas,
    espacos,
    equipamentos,
    grupos,
    solicitacoes,
    agora,
}: PropsDoCalendario) {
    const hoje = hojeDe(agora);
    const idsDosEspacos = espacos.map((espaco) => espaco.id);

    const [selecionados, setSelecionados] = useState<string[]>(() =>
        reconciliarSelecao(lerFiltroDoNavegador(), idsDosEspacos),
    );
    const [mesEscolhido, setMesEscolhido] = useState<string | null>(null);
    const [painel, setPainel] = useState<AlvoDoPainel | null>(null);

    const selecionadosSet = new Set(selecionados);
    const reservasVisiveis = reservas.filter((reserva) =>
        selecionadosSet.has(reserva.espaco_id),
    );
    const espacosVisiveis = espacos.filter((espaco) =>
        selecionadosSet.has(espaco.id),
    );
    const dias = diasDaSemana(semana.inicio);
    const titulo =
        visao === 'dia'
            ? dataPorExtenso(data)
            : intervaloPorExtenso(semana.inicio, semana.fim);

    function navegar(
        novaData: string,
        novaVisao: PropsDoCalendario['visao'] = visao,
    ) {
        setMesEscolhido(null);
        router.visit(
            rota.url({ query: { data: novaData, visao: novaVisao } }),
            { preserveScroll: true },
        );
    }

    function alterarFiltro(ids: string[]) {
        setSelecionados(ids);
        gravarFiltroNoNavegador(ids, idsDosEspacos);
    }

    function abrirNovaReserva(dia: string, hora?: string, espacoId?: string) {
        setPainel({ modo: 'nova', data: dia, hora, espacoId });
    }

    function abrirEdicao(reserva: ReservaDoCalendario) {
        setPainel({ modo: 'editar', reserva });
    }

    function aoSalvarReserva() {
        setPainel(null);
        // Recarrega com a mesma URL, então `data` e `visao` continuam os mesmos.
        router.reload();
    }

    // O painel do assistente leva até aqui com `nova_reserva=1` na URL. Abre a reserva uma vez por visita e limpa a URL.
    const { url: urlDaPagina } = usePage();

    useEffect(() => {
        const pedido = lerPedidoDeNovaReserva(window.location.search);

        if (pedido === null) {
            return;
        }

        setPainel({
            modo: 'nova',
            data,
            hora: pedido.inicio ?? undefined,
            espacoId: pedido.espacoId ?? undefined,
            fim: pedido.fim ?? undefined,
            titulo: pedido.titulo ?? undefined,
            grupoId: pedido.grupoId ?? undefined,
            equipamentos: pedido.equipamentos,
        });
        window.history.replaceState(
            window.history.state,
            '',
            removerParametrosDeNovaReserva(window.location.href),
        );
    }, [urlDaPagina]);

    const passo = PASSO_DA_NAVEGACAO[visao];

    return (
        <>
            <Head title="Calendário" />
            <div className="flex flex-col gap-10 p-4">
                <CabecalhoDaPagina
                    titulo="Calendário"
                    subtitulo="Reservas das salas e espaços da paróquia."
                    acoes={
                        <>
                            <Button
                                variant="outline"
                                className="h-11 rounded-full px-5"
                                onClick={() => window.print()}
                            >
                                <Printer aria-hidden="true" />
                                Imprimir semana
                            </Button>
                            <Button
                                className="h-11 rounded-full px-5"
                                onClick={() => abrirNovaReserva(data)}
                            >
                                <Plus aria-hidden="true" />
                                Nova reserva
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                    <aside
                        aria-label="Filtros"
                        className="flex flex-col gap-4 lg:w-64 lg:shrink-0"
                    >
                        <MiniCalendario
                            mes={mesEscolhido ?? data}
                            selecionado={data}
                            hoje={hoje}
                            onMudarMes={setMesEscolhido}
                            onSelecionarDia={(dia) => navegar(dia)}
                        />
                        <FiltroDeEspacos
                            espacos={espacos}
                            selecionados={selecionados}
                            onAlterar={alterarFiltro}
                        />
                    </aside>

                    <section
                        id="area-de-impressao"
                        aria-label={titulo}
                        className="min-w-0 flex-1 overflow-hidden rounded-2xl border bg-card"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b p-3">
                            <div className="flex flex-wrap items-center gap-1.5 print:hidden">
                                <Button
                                    variant="outline"
                                    className="h-11 px-4 sm:h-9"
                                    onClick={() => navegar(hoje)}
                                >
                                    Hoje
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-11 sm:size-9"
                                    aria-label={
                                        visao === 'dia'
                                            ? 'Dia anterior'
                                            : 'Semana anterior'
                                    }
                                    onClick={() =>
                                        navegar(somarDias(data, -passo))
                                    }
                                >
                                    <ChevronLeft aria-hidden="true" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-11 sm:size-9"
                                    aria-label={
                                        visao === 'dia'
                                            ? 'Próximo dia'
                                            : 'Próxima semana'
                                    }
                                    onClick={() =>
                                        navegar(somarDias(data, passo))
                                    }
                                >
                                    <ChevronRight aria-hidden="true" />
                                </Button>
                            </div>

                            <h2 className="font-serif text-2xl leading-tight font-semibold">
                                {titulo}
                            </h2>

                            <div
                                role="group"
                                aria-label="Visualização"
                                className="flex rounded-xl bg-secondary p-1 print:hidden"
                            >
                                <ToggleDeVisao
                                    ativo={visao === 'dia'}
                                    onClick={() => navegar(data, 'dia')}
                                >
                                    Dia
                                </ToggleDeVisao>
                                <ToggleDeVisao
                                    ativo={visao === 'semana'}
                                    onClick={() => navegar(data, 'semana')}
                                >
                                    Semana
                                </ToggleDeVisao>
                            </div>
                        </div>

                        {visao === 'semana' ? (
                            <div className="hidden md:block">
                                <GradeDaSemana
                                    dias={dias}
                                    hoje={hoje}
                                    agora={agora}
                                    reservas={reservasVisiveis}
                                    espacos={espacos}
                                    onNovaReserva={abrirNovaReserva}
                                    onEditarReserva={abrirEdicao}
                                />
                            </div>
                        ) : (
                            <div className="hidden md:block">
                                <OcupacaoDoDia
                                    data={data}
                                    hoje={hoje}
                                    agora={agora}
                                    espacos={espacosVisiveis}
                                    reservas={reservasVisiveis}
                                    solicitacoes={solicitacoes}
                                    onReservar={abrirNovaReserva}
                                    onEditarReserva={abrirEdicao}
                                />
                            </div>
                        )}

                        <AgendaCelular
                            data={data}
                            hoje={hoje}
                            agora={agora}
                            espacos={espacosVisiveis}
                            reservas={reservasVisiveis}
                            solicitacoes={solicitacoes}
                            onSelecionarDia={(dia) => navegar(dia)}
                            onNovaReserva={(dia) => abrirNovaReserva(dia)}
                            onEditarReserva={abrirEdicao}
                        />
                    </section>
                </div>
            </div>

            {painel !== null && (
                <PainelDeReserva
                    alvo={painel}
                    espacos={espacos}
                    grupos={grupos}
                    equipamentos={equipamentos}
                    onFechar={() => setPainel(null)}
                    onSalvo={aoSalvarReserva}
                />
            )}
        </>
    );
}

function ToggleDeVisao({
    ativo,
    onClick,
    children,
}: {
    ativo: boolean;
    onClick: () => void;
    children: string;
}) {
    return (
        <button
            type="button"
            aria-pressed={ativo}
            onClick={onClick}
            className={cn(
                'flex min-h-11 items-center rounded-lg px-4 text-sm transition-colors sm:min-h-8',
                ativo
                    ? 'bg-card font-semibold text-foreground shadow-xs'
                    : 'text-muted-foreground hover:text-foreground',
            )}
        >
            {children}
        </button>
    );
}

function lerFiltroDoNavegador(): FiltroSalvo | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        return lerFiltroSalvo(window.localStorage.getItem(CHAVE_DO_FILTRO));
    } catch {
        // Storage bloqueado (modo privado, por exemplo): segue com todos os espaços.
        return null;
    }
}

function gravarFiltroNoNavegador(selecionados: string[], idsAtuais: string[]) {
    try {
        window.localStorage.setItem(
            CHAVE_DO_FILTRO,
            JSON.stringify(montarFiltroSalvo(selecionados, idsAtuais)),
        );
    } catch {
        // Sem storage a seleção vale só nesta tela.
    }
}

Calendario.layout = {
    breadcrumbs: [{ title: 'Calendário', href: rota() }],
};
