import { router } from '@inertiajs/react';
import { useState } from 'react';
import { update } from '@/actions/App/Http/Controllers/Agendamentos/ReservaEquipamentoController';
import { tratamentoDeErro } from '@/components/modelos/erro-de-acao';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    rotuloDaAcao,
    rotuloDoMarcar,
    rotuloDoSelo,
} from '@/lib/agenda/equipamentos';
import type { MovimentoDeHoje, PainelDeHoje } from '@/types/agendamentos';

type Props = {
    painel: PainelDeHoje;
    /** Sem o título e a moldura internos, para o pai (ex.: o cartão do Início) já ter os seus. */
    semTitulo?: boolean;
};

/**
 * Lista de levar e recolher do dia. Cada equipamento tem o próprio botão, que faz o PATCH da reserva.
 */
export default function PainelDeEquipamentosDeHoje({
    painel,
    semTitulo = false,
}: Props) {
    /** Chave "reserva-equipamento-acao" enquanto a marcação está indo ao servidor. */
    const [enviando, setEnviando] = useState<string | null>(null);

    const marcar = (movimento: MovimentoDeHoje, equipamentoId: string) => {
        if (enviando !== null) {
            return;
        }

        const chave = `${movimento.reserva_id}-${equipamentoId}-${movimento.acao}`;

        router.patch(
            update.url({
                reserva: movimento.reserva_id,
                equipamento: equipamentoId,
            }),
            { acao: movimento.acao === 'levar' ? 'entregue' : 'recolhido' },
            {
                preserveScroll: true,
                onStart: () => setEnviando(chave),
                onFinish: () => setEnviando(null),
                ...tratamentoDeErro,
            },
        );
    };

    return (
        <section
            aria-labelledby={semTitulo ? undefined : 'hoje-equipamentos'}
            className="flex flex-col gap-3"
        >
            {!semTitulo && (
                <h2
                    id="hoje-equipamentos"
                    className="flex flex-wrap items-baseline gap-x-3 font-serif text-2xl font-semibold"
                >
                    Hoje: separar e recolher
                    <span className="text-sm font-normal text-muted-foreground capitalize">
                        {painel.rotulo}
                    </span>
                </h2>
            )}

            {painel.movimentos.length === 0 ? (
                <p
                    className={cn(
                        'p-6 text-center text-muted-foreground',
                        !semTitulo &&
                            'rounded-2xl border border-dashed bg-card',
                    )}
                >
                    Nenhum equipamento para separar ou recolher hoje.
                </p>
            ) : (
                <ul
                    className={cn(
                        'divide-y overflow-hidden',
                        !semTitulo && 'rounded-2xl border bg-card',
                    )}
                >
                    {painel.movimentos.map((movimento) => {
                        return (
                            <li
                                key={movimento.chave}
                                className="flex flex-col gap-3 p-4 sm:flex-row sm:gap-5"
                            >
                                <span className="w-14 shrink-0 pt-1 font-medium text-muted-foreground tabular-nums">
                                    {movimento.horario}
                                </span>

                                <div className="flex min-w-0 flex-1 flex-col gap-2">
                                    <p>
                                        <span className="font-semibold">
                                            {rotuloDaAcao(movimento.acao)}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            {movimento.espaco} ·{' '}
                                            {movimento.titulo}
                                        </span>
                                    </p>

                                    <ul className="flex flex-col gap-2">
                                        {movimento.itens.map((item) => (
                                            <li
                                                key={item.equipamento_id}
                                                className="flex flex-wrap items-center justify-between gap-2"
                                            >
                                                <span>
                                                    {item.nome}{' '}
                                                    <span className="text-muted-foreground">
                                                        × {item.quantidade}
                                                    </span>
                                                </span>

                                                {item.marcado ? (
                                                    <span className="rounded-full bg-sucesso-fundo px-2.5 py-1 text-xs font-semibold text-sucesso">
                                                        {rotuloDoSelo(
                                                            movimento.acao,
                                                        )}
                                                    </span>
                                                ) : (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="min-h-11"
                                                        aria-label={`${rotuloDoMarcar(movimento.acao)}: ${item.nome}`}
                                                        disabled={
                                                            enviando !== null
                                                        }
                                                        onClick={() =>
                                                            marcar(
                                                                movimento,
                                                                item.equipamento_id,
                                                            )
                                                        }
                                                    >
                                                        {rotuloDoMarcar(
                                                            movimento.acao,
                                                        )}
                                                    </Button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}
