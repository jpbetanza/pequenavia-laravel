import { Link } from '@inertiajs/react';
import { formatarDiaCurto } from '@/lib/agenda/datas';
import { index as solicitacoes } from '@/routes/agendamentos/solicitacoes';
import type { Cartao } from '@/types/assistente';
import { faixaDeHoras } from './formatos';

type Dados = Extract<Cartao, { tipo: 'solicitacoes' }>['dados'];
type Estado = Dados['solicitacoes'][number]['estado'];

const SELOS: Record<
    NonNullable<Estado>,
    { rotulo: string; classes: string }
> = {
    sem_conflito: {
        rotulo: 'Sem conflito',
        classes: 'bg-sucesso-fundo text-sucesso',
    },
    conflito: {
        rotulo: 'Conflito',
        classes: 'bg-destructive/10 text-destructive',
    },
    escolha_um_espaco: {
        rotulo: 'Escolha um espaço',
        classes: 'bg-aviso-fundo text-aviso',
    },
};

export function CartaoDeSolicitacoes({ dados }: { dados: Dados }) {
    return (
        <section
            aria-label="Solicitações"
            className="flex flex-col rounded-xl border bg-background text-sm"
        >
            <p className="border-b px-3 py-2 font-semibold">Solicitações</p>

            {dados.solicitacoes.length === 0 ? (
                <p className="px-3 py-2.5 text-muted-foreground">
                    Nenhuma solicitação encontrada.
                </p>
            ) : (
                <ul className="flex flex-col divide-y">
                    {dados.solicitacoes.map((solicitacao) => {
                        const selo =
                            solicitacao.estado === null
                                ? null
                                : SELOS[solicitacao.estado];

                        return (
                            <li key={solicitacao.id}>
                                <Link
                                    href={solicitacoes.url()}
                                    className="flex min-h-11 flex-col gap-1 px-3 py-2.5 hover:bg-muted/50"
                                >
                                    <span className="flex items-start justify-between gap-2">
                                        <span className="min-w-0 truncate font-semibold">
                                            {solicitacao.titulo}
                                        </span>
                                        {selo !== null && (
                                            <span
                                                className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold ${selo.classes}`}
                                            >
                                                {selo.rotulo}
                                            </span>
                                        )}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {formatarDiaCurto(solicitacao.data)} ·{' '}
                                        {faixaDeHoras(
                                            solicitacao.inicio,
                                            solicitacao.fim,
                                        )}{' '}
                                        ·{' '}
                                        {solicitacao.espaco_nome ??
                                            'Sem espaço escolhido'}
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
