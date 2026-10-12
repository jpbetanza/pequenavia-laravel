import { Link } from '@inertiajs/react';
import { corDoEspaco } from '@/lib/agenda/cores';
import { intervaloPorExtenso } from '@/lib/agenda/datas';
import { calendario } from '@/routes/agendamentos';
import type { Cartao } from '@/types/assistente';
import { faixaDeHoras, rotuloDoDia } from './formatos';

type Dados = Extract<Cartao, { tipo: 'reservas' }>['dados'];
type Reserva = Dados['reservas'][number];

const LIMITE = 8;

/** Agrupa as reservas por data, mantendo a ordem que o servidor mandou. */
function agruparPorData(reservas: Reserva[]): [string, Reserva[]][] {
    const grupos = new Map<string, Reserva[]>();

    for (const reserva of reservas) {
        grupos.set(reserva.data, [
            ...(grupos.get(reserva.data) ?? []),
            reserva,
        ]);
    }

    return Array.from(grupos.entries());
}

export function CartaoDeReservas({ dados }: { dados: Dados }) {
    const visiveis = dados.reservas.slice(0, LIMITE);
    const restantes = dados.reservas.length - visiveis.length;

    return (
        <section
            aria-label="Reservas do período"
            className="flex flex-col rounded-xl border bg-background text-sm"
        >
            <p className="border-b px-3 py-2 font-semibold">
                Reservas de {intervaloPorExtenso(dados.inicio, dados.fim)}
            </p>

            {dados.reservas.length === 0 ? (
                <p className="px-3 py-2.5 text-muted-foreground">
                    Nenhuma reserva neste período.
                </p>
            ) : (
                <div className="flex flex-col">
                    {agruparPorData(visiveis).map(([data, itens]) => (
                        <div key={data} className="flex flex-col">
                            <p className="px-3 pt-2.5 pb-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                {rotuloDoDia(data)}
                            </p>
                            <ul className="flex flex-col">
                                {itens.map((reserva) => {
                                    const cor = corDoEspaco(reserva.espaco_cor);

                                    return (
                                        <li
                                            key={reserva.id}
                                            className="flex items-center gap-2.5 px-3 py-1.5"
                                        >
                                            <span
                                                aria-hidden="true"
                                                className="size-2.5 shrink-0 rounded-[3px]"
                                                style={{
                                                    backgroundColor: cor.forte,
                                                }}
                                            />
                                            <span className="w-24 shrink-0 text-muted-foreground tabular-nums">
                                                {faixaDeHoras(
                                                    reserva.inicio,
                                                    reserva.fim,
                                                )}
                                            </span>
                                            <span className="min-w-0 truncate">
                                                {reserva.titulo}
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {reserva.espaco_nome}
                                                </span>
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    ))}
                </div>
            )}

            {restantes > 0 && (
                <Link
                    href={calendario.url({
                        query: { data: dados.inicio, visao: 'semana' },
                    })}
                    className="inline-flex min-h-11 items-center px-3 font-semibold text-primary underline-offset-4 hover:underline"
                >
                    e mais {restantes}
                </Link>
            )}
        </section>
    );
}
