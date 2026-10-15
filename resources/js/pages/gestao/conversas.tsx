import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    BarraDeResultado,
    LegendaDeResultado,
} from '@/components/gestao/barra-de-resultado';
import { DetalheDaConversa } from '@/components/gestao/detalhe-da-conversa';
import { SeletorDePeriodo } from '@/components/gestao/seletor-de-periodo';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    diaRelativo,
    FRUSTRACAO_ALTA,
    formatarNumero,
    hojeNoFuso,
    rotuloDaFrustracao,
} from '@/lib/gestao/formatar';
import { cn } from '@/lib/utils';
import { index as rotaDeConversas } from '@/routes/gestao/conversas';
import type { ConversasProps, LinhaDaMatriz } from '@/types/gestao';

/** Valor do select que significa "sem filtro". */
const TODOS = '__todos__';

const HORA = new Intl.DateTimeFormat('pt-BR', {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'America/Sao_Paulo',
});

type Parametros = {
    periodo: number;
    caso?: string;
    resultado?: string;
    cliente?: string;
    frustrada?: boolean;
    a_revisar?: boolean;
    conversa?: string;
    page?: number;
};

/** Tira da query o que não tem valor: filtro vazio ou desligado não aparece na URL. */
function limpar(parametros: Parametros): Parametros {
    return Object.fromEntries(
        Object.entries(parametros).filter(
            ([, valor]) => valor !== undefined && valor !== false,
        ),
    ) as Parametros;
}

function quandoDaConversa(iso: string | null, agora: Date): string {
    if (iso === null) {
        return '—';
    }

    const instante = new Date(iso);

    if (Number.isNaN(instante.getTime())) {
        return '—';
    }

    return `${diaRelativo(hojeNoFuso(instante), hojeNoFuso(agora))}, ${HORA.format(instante)}`;
}

/** Tendência da matriz: `pct` é a variação sobre o período anterior; nulo quer dizer que não havia base. */
function tendenciaDaLinha(linha: LinhaDaMatriz): {
    texto: string;
    classe: string;
} {
    if (linha.tendencia_pct === null) {
        return {
            texto: linha.total > 0 ? 'novo' : '—',
            classe: 'text-muted-foreground',
        };
    }

    const pct = Math.round(linha.tendencia_pct);

    if (pct === 0) {
        return { texto: '=', classe: 'text-muted-foreground' };
    }

    return pct > 0
        ? { texto: `↑ ${pct}%`, classe: 'font-semibold text-[#24513F]' }
        : {
              texto: `↓ ${Math.abs(pct)}%`,
              classe: 'font-semibold text-[#A33A2A]',
          };
}

export default function Conversas({
    periodo,
    filtros,
    opcoes,
    matriz,
    conversas,
    a_revisar_total,
    selecionada,
}: ConversasProps) {
    const [agora] = useState(() => new Date());
    const detalheRef = useRef<HTMLDivElement>(null);

    // No celular a lista fica acima do detalhe: ao abrir uma conversa, leva o olhar até ele.
    useEffect(() => {
        if (selecionada && window.matchMedia('(max-width: 1023px)').matches) {
            detalheRef.current?.scrollIntoView({ block: 'start' });
        }
        // Só ao trocar de conversa; mudar filtro ou página não deve rolar a tela.
    }, [selecionada?.id]);

    const atuais: Parametros = limpar({
        periodo,
        caso: filtros.caso ?? undefined,
        resultado: filtros.resultado ?? undefined,
        cliente: filtros.cliente ?? undefined,
        frustrada: filtros.frustrada,
        a_revisar: filtros.a_revisar,
        conversa: selecionada?.id,
    });

    /** Mudar um filtro volta à primeira página e mantém a conversa aberta. */
    function filtrar(alteracao: Partial<Parametros>) {
        router.get(rotaDeConversas.url(), limpar({ ...atuais, ...alteracao }), {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function hrefDaPagina(pagina: number): string {
        return rotaDeConversas.url({
            query: limpar({
                ...atuais,
                page: pagina > 1 ? pagina : undefined,
            }),
        });
    }

    function hrefDaConversa(id: string): string {
        return rotaDeConversas.url({
            query: limpar({
                ...atuais,
                conversa: id,
                page:
                    conversas.current_page > 1
                        ? conversas.current_page
                        : undefined,
            }),
        });
    }

    return (
        <>
            <Head title="Conversas e casos de uso" />

            <div className="flex flex-col gap-6">
                <header className="flex flex-wrap items-end gap-4">
                    <div className="min-w-64 flex-1">
                        <p className="text-xs font-semibold tracking-[0.14em] text-[#8A6420] uppercase">
                            Últimos {periodo} dias
                        </p>
                        <h1 className="mt-1 font-serif text-4xl leading-none font-semibold sm:text-5xl">
                            Conversas e casos de uso
                        </h1>
                    </div>
                    <p className="flex min-h-9 items-center gap-2 rounded-full border border-[#E1D7C8] bg-[#FBF8F2] px-3 text-sm text-[#4A3D38]">
                        Nomes, telefones e e-mails são mascarados · cada
                        abertura fica registrada
                    </p>
                    <SeletorDePeriodo periodo={periodo} />
                </header>

                <section
                    aria-labelledby="titulo-matriz"
                    className="flex flex-col gap-3 rounded-[18px] border border-border bg-card p-5"
                >
                    <div className="flex flex-wrap items-baseline gap-x-4 gap-y-2">
                        <h2
                            id="titulo-matriz"
                            className="flex-1 font-serif text-2xl leading-none font-semibold"
                        >
                            Como cada caso de uso termina
                        </h2>
                        <LegendaDeResultado />
                    </div>

                    {matriz.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nenhuma conversa classificada no período.
                        </p>
                    ) : (
                        <div className="relative overflow-x-auto">
                            <table className="w-full min-w-[36rem] text-sm">
                                <caption className="sr-only">
                                    Resultados de cada caso de uso no período
                                </caption>
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th
                                            scope="col"
                                            className="pb-2 font-semibold"
                                        >
                                            Caso de uso
                                        </th>
                                        <th
                                            scope="col"
                                            className="pb-2 text-right font-semibold"
                                        >
                                            Conversas
                                        </th>
                                        <th
                                            scope="col"
                                            className="pb-2 pl-4 font-semibold"
                                        >
                                            Resultado
                                        </th>
                                        <th
                                            scope="col"
                                            className="pb-2 text-right font-semibold"
                                        >
                                            Tendência
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {matriz.map((linha) => {
                                        const tendencia =
                                            tendenciaDaLinha(linha);

                                        return (
                                            <tr
                                                key={linha.caso}
                                                className="border-t border-[#EDE5D8]"
                                            >
                                                <th
                                                    scope="row"
                                                    className="py-2.5 pr-3 text-left font-normal"
                                                >
                                                    {linha.rotulo}
                                                </th>
                                                <td className="py-2.5 pr-3 text-right font-semibold tabular-nums">
                                                    {formatarNumero(
                                                        linha.total,
                                                    )}
                                                </td>
                                                <td className="w-[45%] py-2.5 pl-4">
                                                    <BarraDeResultado
                                                        resolvida={
                                                            linha.resolvida
                                                        }
                                                        parcial={linha.parcial}
                                                        nao_resolvida={
                                                            linha.nao_resolvida
                                                        }
                                                        aguardando={
                                                            linha.aguardando
                                                        }
                                                    />
                                                </td>
                                                <td
                                                    className={cn(
                                                        'py-2.5 text-right tabular-nums',
                                                        tendencia.classe,
                                                    )}
                                                >
                                                    {tendencia.texto}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
                    <section
                        aria-labelledby="titulo-lista"
                        className="flex min-w-0 flex-col overflow-hidden rounded-[18px] border border-border bg-card"
                    >
                        <div className="flex flex-col gap-3 px-4 pt-4 pb-3">
                            <h2
                                id="titulo-lista"
                                className="font-serif text-2xl leading-none font-semibold"
                            >
                                Conversas
                            </h2>

                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="filtro-caso">
                                        Caso de uso
                                    </Label>
                                    <Select
                                        value={filtros.caso ?? TODOS}
                                        onValueChange={(valor) =>
                                            filtrar({
                                                caso:
                                                    valor === TODOS
                                                        ? undefined
                                                        : valor,
                                            })
                                        }
                                    >
                                        <SelectTrigger
                                            id="filtro-caso"
                                            className="min-h-11 w-full sm:min-h-9"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={TODOS}>
                                                Todos os casos
                                            </SelectItem>
                                            {opcoes.casos.map((opcao) => (
                                                <SelectItem
                                                    key={opcao.valor}
                                                    value={opcao.valor}
                                                >
                                                    {opcao.rotulo}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="filtro-resultado">
                                        Resultado
                                    </Label>
                                    <Select
                                        value={filtros.resultado ?? TODOS}
                                        onValueChange={(valor) =>
                                            filtrar({
                                                resultado:
                                                    valor === TODOS
                                                        ? undefined
                                                        : valor,
                                            })
                                        }
                                    >
                                        <SelectTrigger
                                            id="filtro-resultado"
                                            className="min-h-11 w-full sm:min-h-9"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={TODOS}>
                                                Todos os resultados
                                            </SelectItem>
                                            {opcoes.resultados.map((opcao) => (
                                                <SelectItem
                                                    key={opcao.valor}
                                                    value={opcao.valor}
                                                >
                                                    {opcao.rotulo}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="filtro-cliente">
                                        Paróquia
                                    </Label>
                                    <Select
                                        value={filtros.cliente ?? TODOS}
                                        onValueChange={(valor) =>
                                            filtrar({
                                                cliente:
                                                    valor === TODOS
                                                        ? undefined
                                                        : valor,
                                            })
                                        }
                                    >
                                        <SelectTrigger
                                            id="filtro-cliente"
                                            className="min-h-11 w-full sm:min-h-9"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={TODOS}>
                                                Todas as paróquias
                                            </SelectItem>
                                            {opcoes.clientes.map((cliente) => (
                                                <SelectItem
                                                    key={cliente.id}
                                                    value={cliente.id}
                                                >
                                                    {cliente.nome}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    aria-pressed={filtros.frustrada}
                                    onClick={() =>
                                        filtrar({
                                            frustrada: !filtros.frustrada,
                                        })
                                    }
                                    className={cn(
                                        'inline-flex min-h-11 items-center rounded-full border px-3 text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring sm:min-h-8',
                                        filtros.frustrada
                                            ? 'border-foreground bg-foreground text-background'
                                            : 'border-[#CDBFAC] text-foreground hover:bg-muted',
                                    )}
                                >
                                    Frustradas
                                </button>
                                <button
                                    type="button"
                                    aria-pressed={filtros.a_revisar}
                                    onClick={() =>
                                        filtrar({
                                            a_revisar: !filtros.a_revisar,
                                        })
                                    }
                                    className={cn(
                                        'inline-flex min-h-11 items-center rounded-full border px-3 text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring sm:min-h-8',
                                        filtros.a_revisar
                                            ? 'border-foreground bg-foreground text-background'
                                            : 'border-[#E8D7B0] bg-[#F3E8CF] text-[#5A4214]',
                                    )}
                                >
                                    A revisar ({formatarNumero(a_revisar_total)}
                                    )
                                </button>
                            </div>
                        </div>

                        {conversas.data.length === 0 ? (
                            <p className="border-t px-4 py-4 text-sm text-muted-foreground">
                                Nenhuma conversa com esses filtros neste
                                período.
                            </p>
                        ) : (
                            <ul className="border-t">
                                {conversas.data.map((conversa) => {
                                    const ativa =
                                        selecionada?.id === conversa.id;

                                    return (
                                        <li
                                            key={conversa.id}
                                            className="border-b border-[#EDE5D8] last:border-b-0"
                                        >
                                            <Link
                                                href={hrefDaConversa(
                                                    conversa.id,
                                                )}
                                                preserveState
                                                preserveScroll
                                                aria-current={
                                                    ativa ? 'true' : undefined
                                                }
                                                className={cn(
                                                    'flex min-h-11 w-full flex-col gap-1 px-4 py-3 text-left transition-colors outline-none hover:bg-[#F5F0E6] focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset',
                                                    ativa &&
                                                        'bg-[#F3E8CF] shadow-[inset_3px_0_0_#6E1F24]',
                                                )}
                                            >
                                                <span className="flex items-baseline gap-2">
                                                    <span className="min-w-0 flex-1 font-semibold break-words">
                                                        {conversa.titulo ?? (
                                                            <span className="font-normal text-muted-foreground italic">
                                                                Sem título
                                                            </span>
                                                        )}
                                                    </span>
                                                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                                        {quandoDaConversa(
                                                            conversa.quando,
                                                            agora,
                                                        )}
                                                    </span>
                                                </span>
                                                <span className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                                    {`${conversa.cliente} · ${formatarNumero(conversa.mensagens)} ${conversa.mensagens === 1 ? 'mensagem' : 'mensagens'} · ${conversa.resultado_rotulo}${conversa.frustracao >= FRUSTRACAO_ALTA ? ` · ${rotuloDaFrustracao(conversa.frustracao)}` : ''}`}
                                                    {conversa.a_revisar && (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-[#E8D7B0] bg-[#F3E8CF] text-[#5A4214]"
                                                        >
                                                            a revisar
                                                        </Badge>
                                                    )}
                                                </span>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}

                        <nav
                            aria-label="Paginação das conversas"
                            className="flex flex-wrap items-center justify-between gap-3 border-t border-[#EDE5D8] px-4 py-3 text-sm"
                        >
                            <span className="text-muted-foreground tabular-nums">
                                {conversas.total === 0 ||
                                conversas.from === null ||
                                conversas.to === null
                                    ? 'Nenhuma conversa'
                                    : `Mostrando ${formatarNumero(conversas.from)}–${formatarNumero(conversas.to)} de ${formatarNumero(conversas.total)}`}
                            </span>
                            <div className="flex gap-2">
                                {conversas.prev_page_url ? (
                                    <Link
                                        href={hrefDaPagina(
                                            conversas.current_page - 1,
                                        )}
                                        preserveState
                                        preserveScroll
                                        className={cn(
                                            buttonVariants({
                                                variant: 'outline',
                                                size: 'sm',
                                            }),
                                            'min-h-11 sm:min-h-8',
                                        )}
                                    >
                                        Anterior
                                    </Link>
                                ) : (
                                    <span
                                        aria-disabled="true"
                                        className={cn(
                                            buttonVariants({
                                                variant: 'outline',
                                                size: 'sm',
                                            }),
                                            'min-h-11 opacity-50 sm:min-h-8',
                                        )}
                                    >
                                        Anterior
                                    </span>
                                )}
                                {conversas.next_page_url ? (
                                    <Link
                                        href={hrefDaPagina(
                                            conversas.current_page + 1,
                                        )}
                                        preserveState
                                        preserveScroll
                                        className={cn(
                                            buttonVariants({
                                                variant: 'outline',
                                                size: 'sm',
                                            }),
                                            'min-h-11 sm:min-h-8',
                                        )}
                                    >
                                        Próxima
                                    </Link>
                                ) : (
                                    <span
                                        aria-disabled="true"
                                        className={cn(
                                            buttonVariants({
                                                variant: 'outline',
                                                size: 'sm',
                                            }),
                                            'min-h-11 opacity-50 sm:min-h-8',
                                        )}
                                    >
                                        Próxima
                                    </span>
                                )}
                            </div>
                        </nav>
                    </section>

                    <div ref={detalheRef} className="min-w-0 scroll-mt-4">
                        {selecionada ? (
                            <DetalheDaConversa
                                conversa={selecionada}
                                opcoes={opcoes}
                            />
                        ) : (
                            <section
                                aria-label="Conversa escolhida"
                                className="flex min-h-48 items-center justify-center rounded-[18px] border border-dashed border-border bg-card p-8 text-center text-sm text-muted-foreground"
                            >
                                Escolha uma conversa para ver os detalhes
                            </section>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
