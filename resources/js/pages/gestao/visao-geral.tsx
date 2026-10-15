import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { BarraDeCasos } from '@/components/gestao/barra-de-casos';
import { CartaoNumero } from '@/components/gestao/cartao-numero';
import { GraficoPorDia } from '@/components/gestao/grafico-por-dia';
import { ListaDeAlertas } from '@/components/gestao/lista-de-alertas';
import { SeletorDePeriodo } from '@/components/gestao/seletor-de-periodo';
import { TabelaDeClientes } from '@/components/gestao/tabela-de-clientes';
import {
    dataDeHoje,
    formatarCusto,
    formatarNumero,
    formatarPct,
    tendencia,
} from '@/lib/gestao/formatar';
import { index as clientes } from '@/routes/gestao/clientes';
import { index as conversas } from '@/routes/gestao/conversas';
import type { VisaoGeralProps } from '@/types/gestao';

/** Custo sem o "US$ " repetido, para as parcelas da linha de apoio. */
function parcela(valor: number | null): string {
    return formatarCusto(valor).replace('US$ ', '');
}

export default function VisaoGeral({
    periodo,
    resumo,
    por_dia,
    casos,
    alertas,
    clientes: linhasDeClientes,
}: VisaoGeralProps) {
    const [agora] = useState(() => new Date());
    const variacao = tendencia(resumo.conversas, resumo.conversas_anterior);
    const apoioDaVariacao =
        variacao.texto === 'novo'
            ? 'sem conversas no período anterior'
            : variacao.sentido === 'igual'
              ? 'igual ao período anterior'
              : `${variacao.texto} sobre o período anterior`;
    const pctConfirmadas =
        resumo.acoes_propostas === 0
            ? null
            : Math.round(
                  (resumo.acoes_confirmadas / resumo.acoes_propostas) * 1000,
              ) / 10;

    return (
        <>
            <Head title="Gestão" />

            <div className="flex flex-col gap-6">
                <header className="flex flex-wrap items-end gap-4">
                    <div className="min-w-64 flex-1">
                        <p className="text-xs font-semibold tracking-[0.14em] text-[#8A6420] uppercase">
                            {dataDeHoje(agora)}
                        </p>
                        <h1 className="mt-1 font-serif text-4xl leading-none font-semibold sm:text-5xl">
                            O que se passa nas paróquias
                        </h1>
                    </div>
                    <SeletorDePeriodo periodo={periodo} />
                </header>

                <section
                    aria-label="Resumo do período"
                    className="grid grid-cols-2 gap-3 lg:grid-cols-5 lg:gap-3.5"
                >
                    <CartaoNumero
                        titulo="Paróquias ativas"
                        valor={
                            <>
                                {formatarNumero(resumo.paroquias_ativas)}{' '}
                                <span className="text-lg text-[#8A7A70]">
                                    de {formatarNumero(resumo.paroquias_total)}
                                </span>
                            </>
                        }
                        apoio={
                            resumo.paroquias_novas > 0
                                ? `+${formatarNumero(resumo.paroquias_novas)} novas no período`
                                : 'nenhuma nova no período'
                        }
                        tom={resumo.paroquias_novas > 0 ? 'bom' : 'neutro'}
                    />
                    <CartaoNumero
                        titulo="Conversas com o assistente"
                        valor={formatarNumero(resumo.conversas)}
                        apoio={apoioDaVariacao}
                        tom={variacao.sentido === 'sobe' ? 'bom' : 'neutro'}
                    />
                    <CartaoNumero
                        titulo="Resolvidas"
                        valor={formatarPct(resumo.resolvidas_pct)}
                        apoio={`pelo JEV · ${formatarNumero(resumo.a_revisar)} a revisar`}
                    />
                    <CartaoNumero
                        titulo="Ações confirmadas"
                        valor={
                            <>
                                {formatarNumero(resumo.acoes_confirmadas)}{' '}
                                <span className="text-lg text-[#8A7A70]">
                                    · {formatarPct(pctConfirmadas)}
                                </span>
                            </>
                        }
                        apoio={`das ${formatarNumero(resumo.acoes_propostas)} propostas`}
                    />
                    <CartaoNumero
                        titulo="Custo OpenRouter"
                        valor={formatarCusto(resumo.custo.total)}
                        apoio={`assistente ${parcela(resumo.custo.assistente)} · JEV ${parcela(resumo.custo.jev)} · Haiku ${parcela(resumo.custo.haiku)}`}
                        className="col-span-2 lg:col-span-1"
                    />
                </section>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                    <section
                        aria-labelledby="conversas-por-dia"
                        className="flex min-w-0 flex-col gap-4 rounded-[18px] border border-border bg-card p-5"
                    >
                        <h2
                            id="conversas-por-dia"
                            className="font-serif text-2xl leading-none font-semibold"
                        >
                            Conversas por dia
                        </h2>
                        <GraficoPorDia dias={por_dia} />
                    </section>

                    <section
                        aria-labelledby="casos-de-uso"
                        className="flex min-w-0 flex-col gap-4 rounded-[18px] border border-border bg-card p-5"
                    >
                        <div className="flex items-baseline gap-3">
                            <h2
                                id="casos-de-uso"
                                className="flex-1 font-serif text-2xl leading-none font-semibold"
                            >
                                Casos de uso
                            </h2>
                            <Link
                                href={conversas()}
                                className="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                Ver todos
                            </Link>
                        </div>
                        <BarraDeCasos casos={casos} />
                    </section>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
                    <ListaDeAlertas alertas={alertas} />

                    <section
                        aria-labelledby="clientes"
                        className="flex min-w-0 flex-col gap-3 overflow-hidden rounded-[18px] border border-border bg-card py-5"
                    >
                        <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-5">
                            <h2
                                id="clientes"
                                className="flex-1 font-serif text-2xl leading-none font-semibold"
                            >
                                Clientes
                            </h2>
                            <span className="text-sm text-muted-foreground">
                                ordenados por atividade
                            </span>
                            <Link
                                href={clientes()}
                                className="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                Ver todos
                            </Link>
                        </div>
                        <TabelaDeClientes clientes={linhasDeClientes} />
                    </section>
                </div>
            </div>
        </>
    );
}
