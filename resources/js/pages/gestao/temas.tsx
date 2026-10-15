import { Head, Link } from '@inertiajs/react';
import { DetalheDoTema } from '@/components/gestao/detalhe-do-tema';
import { SeletorDePeriodo } from '@/components/gestao/seletor-de-periodo';
import {
    formatarCusto,
    formatarInstante,
    formatarNumero,
} from '@/lib/gestao/formatar';
import { cn } from '@/lib/utils';
import { index as rotaDosTemas } from '@/routes/gestao/temas';
import type { RodadaDaGestao, TemaLinha, TemasProps } from '@/types/gestao';

function contar(total: number, singular: string, plural: string): string {
    return `${formatarNumero(total)} ${total === 1 ? singular : plural}`;
}

/** Variação da semana: quanto o tema cresceu ou caiu sobre a semana anterior. Crescer é o que pede atenção. */
function tendenciaDaSemana(
    semana: number,
    anterior: number,
): { texto: string; classe: string } {
    const diferenca = semana - anterior;

    if (diferenca === 0) {
        return {
            texto: 'sem mudança na semana',
            classe: 'text-muted-foreground',
        };
    }

    return diferenca > 0
        ? {
              texto: `↑ ${formatarNumero(diferenca)} na semana`,
              classe: 'font-semibold text-[#A33A2A]',
          }
        : {
              texto: `↓ ${formatarNumero(Math.abs(diferenca))} na semana`,
              classe: 'font-semibold text-[#24513F]',
          };
}

function PassoDaRodada({
    numero,
    titulo,
    valor,
    apoio,
    destaque = false,
}: {
    numero: string;
    titulo: string;
    valor: string;
    apoio: string;
    destaque?: boolean;
}) {
    return (
        <li
            className={cn(
                'flex flex-col gap-1 rounded-xl border p-3',
                destaque
                    ? 'border-[#E8D7B0] bg-[#F3E8CF] text-[#3E2D0E]'
                    : 'border-[#EDE5D8] bg-background',
            )}
        >
            <span className="text-xs font-semibold text-[#8A6420]">
                {numero} · {titulo}
            </span>
            <span className="font-serif text-2xl leading-tight font-semibold tabular-nums">
                {valor}
            </span>
            <span className="text-sm leading-snug">{apoio}</span>
        </li>
    );
}

function PainelDaRodada({ rodada }: { rodada: RodadaDaGestao | null }) {
    return (
        <section
            aria-labelledby="titulo-rodada"
            className="flex flex-col gap-3 rounded-[18px] border border-border bg-card p-5"
        >
            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2
                    id="titulo-rodada"
                    className="flex-1 font-serif text-2xl leading-none font-semibold"
                >
                    {rodada
                        ? `A última rodada, ${formatarInstante(rodada.iniciada_em)}`
                        : 'A rodada'}
                </h2>
                <p className="text-sm text-muted-foreground">
                    Rodadas às 05:00 e às 17:00 · só conversas paradas há mais
                    de 1 hora
                </p>
            </div>

            {rodada === null ? (
                <p className="text-sm text-muted-foreground">
                    Nenhuma rodada ainda. A primeira acontece às 05:00 ou às
                    17:00.
                </p>
            ) : (
                <>
                    {rodada.erro && (
                        <div
                            role="alert"
                            className="rounded-xl border border-[#E9B8AE] bg-[#FBE9E5] px-4 py-3 text-sm text-[#7A2A1E]"
                        >
                            <p className="font-semibold">
                                A rodada teve um erro.
                            </p>
                            <p>{rodada.erro}</p>
                        </div>
                    )}

                    <ol className="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                        <PassoDaRodada
                            numero="1"
                            titulo="Código"
                            valor={contar(
                                rodada.conversas_lidas,
                                'conversa',
                                'conversas',
                            )}
                            apoio="mascara nomes, telefones e e-mails; conta ferramentas, ações e tokens"
                        />
                        <PassoDaRodada
                            numero="2"
                            titulo="JEV, em todas"
                            valor={`${formatarNumero(rodada.conversas_lidas)} rotuladas`}
                            apoio={`caso de uso, resultado, frustração e pedido não atendido · ${formatarCusto(rodada.custo_jev)}${rodada.falhas > 0 ? ` · ${formatarNumero(rodada.falhas)} com falha` : ''}`}
                        />
                        <PassoDaRodada
                            numero="3"
                            titulo="Haiku, só nas que valem leitura"
                            valor={`${formatarNumero(rodada.resumidas)} resumidas`}
                            apoio={`não resolvidas, pedido fora do app ou confiança baixa · ${formatarCusto(rodada.custo_haiku)}`}
                        />
                        <PassoDaRodada
                            numero="4"
                            titulo="Temas"
                            valor={
                                rodada.temas_novos === 1
                                    ? '1 novo'
                                    : `${formatarNumero(rodada.temas_novos)} novos`
                            }
                            apoio="nomes criados pelo Haiku viram opções do JEV na próxima rodada"
                            destaque={rodada.temas_novos > 0}
                        />
                    </ol>

                    {rodada.terminada_em === null && (
                        <p className="text-sm text-muted-foreground">
                            A rodada ainda está em andamento.
                        </p>
                    )}
                </>
            )}
        </section>
    );
}

function ListaDeTemas({
    temas,
    periodo,
    selecionadoId,
}: {
    temas: TemaLinha[];
    periodo: number;
    selecionadoId: string | null;
}) {
    return (
        <section
            aria-labelledby="titulo-temas"
            className="flex min-w-0 flex-col overflow-hidden rounded-[18px] border border-border bg-card"
        >
            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-5 pt-4 pb-2">
                <h2
                    id="titulo-temas"
                    className="flex-1 font-serif text-2xl leading-none font-semibold"
                >
                    Temas · {periodo} dias
                </h2>
                <span className="text-sm text-muted-foreground">
                    por número de conversas
                </span>
            </div>

            {temas.length === 0 ? (
                <p className="border-t border-[#EDE5D8] px-5 py-4 text-sm text-muted-foreground">
                    Nenhum pedido não atendido neste período.
                </p>
            ) : (
                <ul className="border-t border-[#EDE5D8]">
                    {temas.map((tema) => {
                        const ativo = tema.id === selecionadoId;
                        const tendencia = tendenciaDaSemana(
                            tema.semana,
                            tema.semana_anterior,
                        );

                        return (
                            <li
                                key={tema.id}
                                className="border-b border-[#EDE5D8] last:border-b-0"
                            >
                                <Link
                                    href={rotaDosTemas.url({
                                        query: { periodo, tema: tema.id },
                                    })}
                                    preserveState
                                    preserveScroll
                                    aria-current={ativo ? 'true' : undefined}
                                    className={cn(
                                        'flex min-h-11 w-full flex-col gap-1 px-5 py-3 transition-colors outline-none hover:bg-[#F5F0E6] focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset',
                                        ativo &&
                                            'bg-[#F3E8CF] shadow-[inset_3px_0_0_#6E1F24]',
                                    )}
                                >
                                    <span className="flex items-baseline gap-2">
                                        <span className="min-w-0 flex-1 font-semibold break-words">
                                            {tema.nome}
                                        </span>
                                        {tema.situacao === 'novo' && (
                                            <span className="rounded-full bg-[#D9B97A] px-2 text-xs font-semibold text-[#221A17]">
                                                novo
                                            </span>
                                        )}
                                        <span className="font-semibold tabular-nums">
                                            {formatarNumero(tema.conversas)}
                                        </span>
                                    </span>
                                    <span className="flex flex-wrap gap-x-3 gap-y-0.5 text-sm text-muted-foreground">
                                        <span className="flex-1">
                                            {contar(
                                                tema.paroquias,
                                                'paróquia',
                                                'paróquias',
                                            )}
                                            {' · '}
                                            {formatarNumero(
                                                tema.frustradas,
                                            )}{' '}
                                            com frustração
                                        </span>
                                        <span className={tendencia.classe}>
                                            {tendencia.texto}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

export default function Temas({
    periodo,
    rodada,
    temas,
    selecionado,
}: TemasProps) {
    return (
        <>
            <Head title="Pedidos não atendidos" />

            <div className="flex flex-col gap-6">
                <header className="flex flex-wrap items-end gap-4">
                    <div className="min-w-64 flex-1">
                        <p className="text-xs font-semibold tracking-[0.14em] text-[#8A6420] uppercase">
                            O que as secretarias pedem e o app ainda não faz
                        </p>
                        <h1 className="mt-1 font-serif text-4xl leading-none font-semibold sm:text-5xl">
                            Pedidos não atendidos
                        </h1>
                    </div>
                    <SeletorDePeriodo periodo={periodo} />
                </header>

                <PainelDaRodada rodada={rodada} />

                <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                    <ListaDeTemas
                        temas={temas}
                        periodo={periodo}
                        selecionadoId={selecionado?.tema.id ?? null}
                    />

                    {selecionado ? (
                        <DetalheDoTema
                            selecionado={selecionado}
                            temas={temas}
                        />
                    ) : (
                        <section
                            aria-label="Tema escolhido"
                            className="flex min-h-48 items-center justify-center rounded-[18px] border border-dashed border-border bg-card p-8 text-center text-sm text-muted-foreground"
                        >
                            Escolha um tema para ver os detalhes
                        </section>
                    )}
                </div>
            </div>
        </>
    );
}
