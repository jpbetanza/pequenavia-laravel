import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { corDoEspaco } from '@/lib/agenda/cores';
import { horaCurta } from '@/lib/inicio/resumo';
import { cn } from '@/lib/utils';
import { calendario } from '@/routes/agendamentos';
import type { ReservaDoInicio } from '@/types/inicio';

type Props = {
    reservas: ReservaDoInicio[];
};

function faixa(reserva: ReservaDoInicio): string {
    return `${horaCurta(reserva.inicio)}–${horaCurta(reserva.fim)}`;
}

function Situacao({ reserva }: { reserva: ReservaDoInicio }) {
    if (reserva.situacao === 'encerrada') {
        return <span className="text-xs text-muted-foreground">Encerrada</span>;
    }

    if (reserva.situacao === 'em_andamento') {
        const cores = corDoEspaco(reserva.espaco.cor);

        return (
            <span
                className="rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap"
                style={{ backgroundColor: cores.fundo, color: cores.texto }}
            >
                Em andamento
            </span>
        );
    }

    const primeiro = reserva.equipamentos[0];

    if (primeiro === undefined) {
        return null;
    }

    return (
        <span className="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap text-secondary-foreground">
            {primeiro.nome} ×{primeiro.quantidade}
        </span>
    );
}

/** Reservas do dia no espaço do usuário, com a faixa "Agora" da que está acontecendo. */
export function HojeNasSalas({ reservas }: Props) {
    const emAndamento = reservas.find(
        (reserva) => reserva.situacao === 'em_andamento',
    );

    return (
        <section
            aria-labelledby="hoje-nas-salas"
            className="flex flex-col gap-4 rounded-[18px] border border-border bg-card p-5 sm:p-6"
        >
            <div className="flex items-baseline justify-between gap-3">
                <h2
                    id="hoje-nas-salas"
                    className="font-serif text-3xl leading-none font-semibold"
                >
                    Hoje nas salas
                </h2>
                <Link
                    href={calendario({ query: { visao: 'dia' } })}
                    prefetch
                    className="inline-flex min-h-11 items-center font-semibold underline-offset-4 hover:underline"
                >
                    Ver ocupação →
                </Link>
            </div>

            {emAndamento !== undefined && (
                <div
                    className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm"
                    style={{
                        backgroundColor: corDoEspaco(emAndamento.espaco.cor)
                            .fundo,
                        color: corDoEspaco(emAndamento.espaco.cor).texto,
                    }}
                >
                    <span className="text-xs font-bold tracking-widest uppercase">
                        Agora
                    </span>
                    <span className="min-w-0 flex-1">
                        <b>{emAndamento.titulo}</b> · {emAndamento.espaco.nome},
                        até {horaCurta(emAndamento.fim)}
                    </span>
                </div>
            )}

            {reservas.length === 0 ? (
                <p className="py-4 text-muted-foreground">
                    Nenhuma reserva hoje.
                </p>
            ) : (
                <ol className="flex flex-col">
                    {reservas.map((reserva) => {
                        const cores = corDoEspaco(reserva.espaco.cor);
                        const encerrada = reserva.situacao === 'encerrada';

                        return (
                            <li
                                key={reserva.id}
                                className={cn(
                                    'grid grid-cols-[72px_minmax(0,1fr)_auto] items-center gap-3 border-t border-border py-3',
                                    encerrada &&
                                        'text-muted-foreground opacity-60',
                                )}
                            >
                                <span className="font-semibold">
                                    {faixa(reserva)}
                                </span>
                                <span className="flex min-w-0 items-center gap-2.5">
                                    <i
                                        aria-hidden="true"
                                        className="h-8 w-1 shrink-0 rounded"
                                        style={{ backgroundColor: cores.forte }}
                                    />
                                    <span className="min-w-0">
                                        <b className="block truncate">
                                            {reserva.titulo}
                                        </b>
                                        <span className="block truncate text-sm text-muted-foreground">
                                            {reserva.grupo_nome !== null &&
                                                `${reserva.grupo_nome} · `}
                                            {reserva.espaco.nome}
                                        </span>
                                    </span>
                                </span>
                                <Situacao reserva={reserva} />
                            </li>
                        );
                    })}
                </ol>
            )}

            <div className="flex flex-wrap gap-2.5">
                <Button asChild className="min-h-11 px-4">
                    <Link
                        href={calendario({ query: { nova_reserva: '1' } })}
                        prefetch
                    >
                        Nova reserva
                    </Link>
                </Button>
                <Button asChild variant="outline" className="min-h-11 px-4">
                    <Link href={calendario()} prefetch>
                        Abrir a semana
                    </Link>
                </Button>
            </div>
        </section>
    );
}
