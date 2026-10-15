import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { CartaoNumero } from '@/components/gestao/cartao-numero';
import { SeletorDePeriodo } from '@/components/gestao/seletor-de-periodo';
import {
    diaRelativo,
    diasEntre,
    FRUSTRACAO_ALTA,
    formatarCusto,
    formatarData,
    formatarInstante,
    formatarNumero,
    formatarPct,
    hojeNoFuso,
    rotuloDaFrustracao,
} from '@/lib/gestao/formatar';
import { index as conversas } from '@/routes/gestao/conversas';
import { index as clientes } from '@/routes/gestao/clientes';
import { index as temas } from '@/routes/gestao/temas';
import type { ClienteProps, SituacaoDoCliente } from '@/types/gestao';

/** Cores da situação: fundo e texto da pílula, e o ponto que repete o nível em texto. */
const SITUACAO: Record<
    SituacaoDoCliente['nivel'],
    { fundo: string; texto: string; ponto: string }
> = {
    bem: { fundo: '#E2EEE8', texto: '#1B3D30', ponto: '#24513F' },
    atencao: { fundo: '#F3E8CF', texto: '#5A4214', ponto: '#B8892F' },
    parado: { fundo: '#F6E1DC', texto: '#7A2418', ponto: '#A33A2A' },
};

const COR_DO_RESULTADO: Record<string, string> = {
    resolvida: 'text-sucesso',
    parcial: 'text-aviso',
    nao_resolvida: 'text-[#A33A2A]',
};

const COR_DO_TOM: Record<'bom' | 'ruim' | 'neutro', string> = {
    bom: '#24513F',
    ruim: '#A33A2A',
    neutro: '#CDBFAC',
};

const ROTULO_DO_TOM: Record<'bom' | 'ruim' | 'neutro', string> = {
    bom: 'Positivo',
    ruim: 'Negativo',
    neutro: 'Neutro',
};

/** `15/10/2026`, a data por extenso com ano, usada na linha de apoio do cabeçalho. */
function dataComAno(data: string | null): string {
    const partes = data?.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    return partes ? `${partes[3]}/${partes[2]}/${partes[1]}` : '—';
}

/** Hoje, ontem ou a data curta: como a linha do tempo mostra quando algo aconteceu. */
function quandoNaLinha(data: string | null, hoje: string): string | null {
    if (data === null) {
        return null;
    }

    const dias = diasEntre(data, hoje);

    if (dias === 0) {
        return 'Hoje';
    }

    if (dias === 1) {
        return 'Ontem';
    }

    return formatarData(data);
}

/** Casos de uso do cliente: uma barra por caso, com o tamanho relativo ao caso mais frequente. */
function ListaDeCasos({ casos }: { casos: ClienteProps['casos'] }) {
    if (casos.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nenhuma conversa classificada no período.
            </p>
        );
    }

    const maior = Math.max(1, ...casos.map((caso) => caso.total));

    return (
        <ul className="flex flex-col gap-2.5">
            {casos.map((caso) => {
                const proporcao = (parte: number) =>
                    caso.total === 0 ? 0 : (parte / caso.total) * 100;

                return (
                    <li
                        key={caso.rotulo}
                        className="grid grid-cols-[8.5rem_minmax(0,1fr)_2.5rem] items-center gap-2.5 text-sm sm:grid-cols-[11rem_minmax(0,1fr)_3rem]"
                    >
                        <span className="leading-snug">{caso.rotulo}</span>
                        <span
                            aria-hidden="true"
                            className="flex h-2.5 overflow-hidden rounded-[4px] bg-secondary"
                            style={{ width: `${(caso.total / maior) * 100}%` }}
                        >
                            <span
                                className="h-full"
                                style={{
                                    width: `${proporcao(caso.resolvida)}%`,
                                    background: '#24513F',
                                }}
                            />
                            <span
                                className="h-full"
                                style={{
                                    width: `${proporcao(caso.parcial)}%`,
                                    background: '#D9B97A',
                                }}
                            />
                            <span
                                className="h-full"
                                style={{
                                    width: `${proporcao(caso.nao_resolvida)}%`,
                                    background: '#A33A2A',
                                }}
                            />
                        </span>
                        <span className="text-right font-semibold tabular-nums">
                            {formatarNumero(caso.total)}
                        </span>
                        <span className="sr-only">
                            {`${formatarNumero(caso.resolvida)} resolvidas, ${formatarNumero(caso.parcial)} parciais e ${formatarNumero(caso.nao_resolvida)} não resolvidas`}
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}

export default function Cliente({
    periodo,
    cliente,
    situacao,
    configuracao,
    cadastros,
    reservas,
    assistente,
    casos,
    trava,
    linha_do_tempo,
    conversas: conversasRecentes,
}: ClienteProps) {
    const [agora] = useState(() => new Date());
    const hoje = hojeNoFuso(agora);
    const cor = SITUACAO[situacao.nivel];
    const pendentes =
        configuracao?.passos
            .filter((passo) => !passo.feito && !passo.pulado)
            .map((passo) => passo.titulo) ?? [];

    return (
        <>
            <Head title={cliente.nome} />

            <div className="flex flex-col gap-5">
                <Link
                    href={clientes()}
                    className="inline-flex min-h-11 items-center self-start rounded-sm text-sm font-semibold text-[#6E1F24] underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                >
                    ← Clientes
                </Link>

                <header className="flex flex-wrap items-end gap-4">
                    <div className="flex min-w-64 flex-1 flex-col gap-1.5">
                        <h1 className="font-serif text-4xl leading-none font-semibold sm:text-5xl">
                            {cliente.nome}
                        </h1>
                        <p className="text-muted-foreground">
                            {cliente.email} · cliente desde{' '}
                            {dataComAno(cliente.desde)} · último acesso{' '}
                            {cliente.ultimo_acesso === null
                                ? 'nunca'
                                : diaRelativo(cliente.ultimo_acesso, hoje)}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <span
                            className="inline-flex min-h-11 items-center gap-2 rounded-full px-4 text-sm font-semibold"
                            style={{
                                background: cor.fundo,
                                color: cor.texto,
                            }}
                        >
                            <span
                                aria-hidden="true"
                                className="size-2 rounded-full"
                                style={{ background: cor.ponto }}
                            />
                            {situacao.texto}
                        </span>
                        <SeletorDePeriodo periodo={periodo} />
                    </div>
                </header>

                <section
                    aria-label="Resumo do cliente"
                    className="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-3.5"
                >
                    <CartaoNumero
                        titulo="Configuração"
                        valor={
                            configuracao
                                ? `${configuracao.feitos} de ${configuracao.total}`
                                : '—'
                        }
                        apoio={
                            configuracao === null
                                ? 'sem passos de configuração'
                                : pendentes.length === 0
                                  ? 'tudo pronto'
                                  : `faltam ${pendentes.join(', ')}`
                        }
                        tom={
                            configuracao !== null && pendentes.length === 0
                                ? 'bom'
                                : 'neutro'
                        }
                    />
                    <CartaoNumero
                        titulo="Cadastros"
                        valor={`${formatarNumero(cadastros.espacos)} · ${formatarNumero(cadastros.equipamentos)} · ${formatarNumero(cadastros.grupos)}`}
                        apoio="espaços · equipamentos · grupos"
                    />
                    <CartaoNumero
                        titulo="Reservas no período"
                        valor={formatarNumero(reservas.total)}
                        apoio={`${formatarNumero(reservas.pelo_assistente)} pelo assistente · ${formatarNumero(reservas.pelo_formulario)} pelo formulário`}
                    />
                    <CartaoNumero
                        titulo="Assistente no período"
                        valor={
                            <>
                                {formatarNumero(assistente.conversas)}{' '}
                                <span className="text-lg text-[#8A7A70]">
                                    · {formatarPct(assistente.resolvidas_pct)}
                                </span>
                            </>
                        }
                        apoio={`conversas · resolvidas · custo ${formatarCusto(assistente.custo)}`}
                    />
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section
                        aria-labelledby="para-que-usa"
                        className="flex min-w-0 flex-col gap-4 rounded-[18px] border border-border bg-card p-5"
                    >
                        <div className="flex flex-col gap-2">
                            <h2
                                id="para-que-usa"
                                className="font-serif text-2xl leading-none font-semibold"
                            >
                                Para que usa o assistente
                            </h2>
                            <p className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1.5">
                                    <span
                                        aria-hidden="true"
                                        className="size-2.5 rounded-[3px]"
                                        style={{ background: '#24513F' }}
                                    />
                                    Resolvida
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span
                                        aria-hidden="true"
                                        className="size-2.5 rounded-[3px]"
                                        style={{ background: '#D9B97A' }}
                                    />
                                    Parcial
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span
                                        aria-hidden="true"
                                        className="size-2.5 rounded-[3px]"
                                        style={{ background: '#A33A2A' }}
                                    />
                                    Não resolvida
                                </span>
                            </p>
                        </div>

                        <ListaDeCasos casos={casos} />

                        {trava !== null && (
                            <div className="flex flex-col gap-2 rounded-[10px] bg-[#F3E8CF] p-3 text-sm text-[#3E2D0E]">
                                <p>
                                    Das conversas não resolvidas,{' '}
                                    <b>
                                        {formatarNumero(trava.conversas)} pedem
                                        «{trava.tema}»
                                    </b>
                                    . É a maior trava desta paróquia.
                                </p>
                                <Link
                                    href={temas()}
                                    className="inline-flex min-h-11 items-center self-start rounded-sm font-semibold text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    Ver em Pedidos não atendidos
                                </Link>
                            </div>
                        )}
                    </section>

                    <section
                        aria-labelledby="linha-do-tempo"
                        className="flex min-w-0 flex-col gap-4 rounded-[18px] border border-border bg-card p-5"
                    >
                        <h2
                            id="linha-do-tempo"
                            className="font-serif text-2xl leading-none font-semibold"
                        >
                            Linha do tempo
                        </h2>

                        {linha_do_tempo.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nada aconteceu ainda.
                            </p>
                        ) : (
                            <ol className="ml-2 flex flex-col gap-3.5 border-l-2 border-border pl-5">
                                {linha_do_tempo.map((item, indice) => {
                                    const quando = quandoNaLinha(
                                        item.data,
                                        hoje,
                                    );

                                    return (
                                        <li
                                            key={`${item.data}-${indice}`}
                                            className="relative text-sm leading-snug"
                                        >
                                            <span
                                                aria-hidden="true"
                                                className="absolute top-1.5 -left-[27px] size-3 rounded-full border-2 border-card"
                                                style={{
                                                    background:
                                                        COR_DO_TOM[item.tom],
                                                }}
                                            />
                                            <span className="sr-only">
                                                {ROTULO_DO_TOM[item.tom]}:{' '}
                                            </span>
                                            {quando !== null && (
                                                <>
                                                    <b>{quando}</b> ·{' '}
                                                </>
                                            )}
                                            {item.texto}
                                        </li>
                                    );
                                })}
                            </ol>
                        )}
                    </section>
                </div>

                <section
                    aria-labelledby="conversas-recentes"
                    className="flex flex-col overflow-hidden rounded-[18px] border border-border bg-card"
                >
                    <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-5 pt-4 pb-2">
                        <h2
                            id="conversas-recentes"
                            className="flex-1 font-serif text-2xl leading-none font-semibold"
                        >
                            Conversas recentes
                        </h2>
                        <Link
                            href={conversas({
                                query: { cliente: cliente.id },
                            })}
                            className="inline-flex min-h-11 items-center rounded-sm text-sm font-semibold text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            Abrir em Conversas
                        </Link>
                    </div>

                    <div className="relative overflow-x-auto">
                        <table className="w-full min-w-[640px] border-collapse text-sm tabular-nums">
                            <caption className="sr-only">
                                Últimas conversas da paróquia com o assistente
                            </caption>
                            <thead>
                                <tr className="text-left text-muted-foreground">
                                    <th
                                        scope="col"
                                        className="px-5 py-2 font-semibold"
                                    >
                                        Conversa
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-2 py-2 font-semibold"
                                    >
                                        Caso de uso
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-2 py-2 font-semibold"
                                    >
                                        Resultado
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-2 py-2 font-semibold"
                                    >
                                        Frustração
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 pr-5 pl-2 font-semibold"
                                    >
                                        Quando
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {conversasRecentes.length === 0 && (
                                    <tr className="border-t border-border">
                                        <td
                                            colSpan={5}
                                            className="px-5 py-4 text-muted-foreground"
                                        >
                                            Nenhuma conversa com o assistente
                                            ainda.
                                        </td>
                                    </tr>
                                )}
                                {conversasRecentes.map((conversa) => {
                                    const frustrada =
                                        conversa.frustracao >= FRUSTRACAO_ALTA;

                                    return (
                                        <tr
                                            key={conversa.id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-5 py-2.5 font-semibold">
                                                <Link
                                                    href={conversas({
                                                        query: {
                                                            conversa:
                                                                conversa.id,
                                                        },
                                                    })}
                                                    className="inline-flex min-h-11 items-center rounded-sm text-foreground underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                                >
                                                    {conversa.titulo}
                                                </Link>
                                            </td>
                                            <td className="px-2 py-2.5">
                                                {conversa.caso_rotulo}
                                            </td>
                                            <td
                                                className={`px-2 py-2.5 font-semibold ${COR_DO_RESULTADO[conversa.resultado] ?? 'text-muted-foreground'}`}
                                            >
                                                {conversa.resultado_rotulo}
                                            </td>
                                            <td
                                                className={
                                                    frustrada
                                                        ? 'px-2 py-2.5 font-semibold text-[#A33A2A]'
                                                        : 'px-2 py-2.5'
                                                }
                                            >
                                                {rotuloDaFrustracao(
                                                    conversa.frustracao,
                                                )}
                                            </td>
                                            <td className="py-2.5 pr-5 pl-2">
                                                {formatarInstante(
                                                    conversa.quando,
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}
