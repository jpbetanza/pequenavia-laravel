import { Link } from '@inertiajs/react';
import { corDoEspaco } from '@/lib/agenda/cores';
import { calendario } from '@/routes/agendamentos';
import type { Cartao } from '@/types/assistente';
import { faixaDeHoras, rotuloDoDia } from './formatos';

type Dados = Extract<Cartao, { tipo: 'disponibilidade' }>['dados'];

export function CartaoDeDisponibilidade({ dados }: { dados: Dados }) {
    const cor = corDoEspaco(dados.espaco.cor);
    const pedido = dados.pedido;

    return (
        <section
            aria-label={`Disponibilidade de ${dados.espaco.nome}`}
            className="overflow-hidden rounded-xl border bg-background text-sm"
        >
            <div className="flex items-center justify-between gap-3 border-b px-3 py-2">
                <p className="flex min-w-0 items-center gap-2 font-semibold">
                    <span
                        aria-hidden="true"
                        className="size-2.5 shrink-0 rounded-[3px]"
                        style={{ backgroundColor: cor.forte }}
                    />
                    <span className="truncate">
                        {dados.espaco.nome} · {rotuloDoDia(dados.data)}
                    </span>
                </p>
                <Link
                    href={calendario.url({
                        query: { data: dados.data, visao: 'dia' },
                    })}
                    className="inline-flex min-h-11 shrink-0 items-center font-semibold text-primary underline-offset-4 hover:underline"
                >
                    Ver o dia
                </Link>
            </div>

            {dados.reservas.length === 0 ? (
                <p className="px-3 py-2.5 text-muted-foreground">
                    Sem reservas neste dia.
                </p>
            ) : (
                <ul className="flex flex-col">
                    {dados.reservas.map((reserva) => (
                        <li
                            key={`${reserva.inicio}-${reserva.titulo}`}
                            className="flex gap-3 px-3 py-2"
                        >
                            <span className="w-24 shrink-0 text-muted-foreground tabular-nums">
                                {faixaDeHoras(reserva.inicio, reserva.fim)}
                            </span>
                            <span className="min-w-0">{reserva.titulo}</span>
                        </li>
                    ))}
                </ul>
            )}

            {pedido !== null && pedido.conflitos.length === 0 && (
                <p className="bg-sucesso-fundo px-3 py-2 font-semibold text-sucesso tabular-nums">
                    {faixaDeHoras(pedido.inicio, pedido.fim)} · Livre
                </p>
            )}

            {pedido !== null && pedido.conflitos.length > 0 && (
                <div className="flex flex-col gap-1.5 px-3 py-2 text-destructive">
                    <p className="font-semibold">
                        Não está livre em{' '}
                        {faixaDeHoras(pedido.inicio, pedido.fim)}
                    </p>
                    <ul className="list-disc pl-5">
                        {pedido.conflitos.map((conflito) => (
                            <li key={conflito.mensagem}>{conflito.mensagem}</li>
                        ))}
                    </ul>
                    {pedido.sugestoes.espacos.length > 0 && (
                        <p className="text-foreground">
                            Espaços livres:{' '}
                            {pedido.sugestoes.espacos
                                .map((espaco) => espaco.nome)
                                .join(', ')}
                            .
                        </p>
                    )}
                    {pedido.sugestoes.horario !== null && (
                        <p className="text-foreground">
                            Horário sugerido:{' '}
                            {faixaDeHoras(
                                pedido.sugestoes.horario.inicio,
                                pedido.sugestoes.horario.fim,
                            )}
                            .
                        </p>
                    )}
                </div>
            )}
        </section>
    );
}
