import { Link } from '@inertiajs/react';
import { corDoEspaco } from '@/lib/agenda/cores';
import { dataCurta, horaCurta } from '@/lib/inicio/resumo';
import { cn } from '@/lib/utils';
import { index as solicitacoes } from '@/routes/agendamentos/solicitacoes';
import type { CartaoDoInicio } from '@/types/inicio';

type Props = {
    total: number;
    primeiras: CartaoDoInicio[];
};

function Estado({ cartao }: { cartao: CartaoDoInicio }) {
    if (cartao.estado === 'conflito') {
        const quantidade = cartao.conflitos.length;

        return (
            <span className="rounded-full bg-aviso-fundo px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap text-aviso">
                {quantidade === 1 ? '1 conflito' : `${quantidade} conflitos`}
            </span>
        );
    }

    if (cartao.estado === 'sem_conflito') {
        return (
            <span className="rounded-full bg-sucesso-fundo px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap text-sucesso">
                Sem conflito
            </span>
        );
    }

    if (cartao.estado === 'escolha_um_espaco') {
        return (
            <span className="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap text-secondary-foreground">
                Escolher espaço
            </span>
        );
    }

    return null;
}

/** Solicitações pendentes, as três primeiras, com o estado de conflito de cada uma. */
export function PedidosDoInicio({ total, primeiras }: Props) {
    return (
        <section
            aria-labelledby="pedidos-do-inicio"
            className="flex flex-col gap-3 rounded-[18px] border border-border bg-card p-5 sm:p-6"
        >
            <div className="flex items-baseline justify-between gap-3">
                <h2
                    id="pedidos-do-inicio"
                    className="font-serif text-3xl leading-none font-semibold"
                >
                    Pedidos esperando você
                </h2>
                <Link
                    href={solicitacoes()}
                    prefetch
                    className="font-semibold whitespace-nowrap underline-offset-4 hover:underline"
                >
                    {total === 0
                        ? 'Ver solicitações →'
                        : `Ver todos (${total}) →`}
                </Link>
            </div>

            {primeiras.length === 0 ? (
                <p className="py-2 text-muted-foreground">
                    Nenhum pedido esperando resposta.
                </p>
            ) : (
                <ul className="flex flex-col">
                    {primeiras.map((cartao) => (
                        <li
                            key={cartao.id}
                            className="flex items-center gap-3 border-t border-border py-3"
                        >
                            <i
                                aria-hidden="true"
                                className={cn(
                                    'size-2.5 shrink-0 rounded-[3px]',
                                    cartao.espaco_cor === null && 'bg-border',
                                )}
                                style={
                                    cartao.espaco_cor === null
                                        ? undefined
                                        : {
                                              backgroundColor: corDoEspaco(
                                                  cartao.espaco_cor,
                                              ).forte,
                                          }
                                }
                            />
                            <span className="min-w-0 flex-1">
                                <b className="block truncate">
                                    {cartao.titulo}
                                    {cartao.grupo_nome !== null &&
                                        ` · ${cartao.grupo_nome}`}
                                </b>
                                <span className="block truncate text-sm text-muted-foreground">
                                    {cartao.espaco_nome ?? 'Sem espaço'} ·{' '}
                                    {dataCurta(cartao.data)},{' '}
                                    {horaCurta(cartao.inicio)}–
                                    {horaCurta(cartao.fim)}
                                </span>
                            </span>
                            <Estado cartao={cartao} />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
