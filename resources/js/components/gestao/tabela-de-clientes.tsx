import { Link } from '@inertiajs/react';
import {
    diaRelativo,
    diasEntre,
    formatarNumero,
    formatarPct,
    hojeNoFuso,
} from '@/lib/gestao/formatar';
import { show } from '@/routes/gestao/clientes';
import type { ClienteLinha } from '@/types/gestao';

/** Mesmo limite de `dias_sem_acesso_alerta` em config/gestao.php: depois disso, o último acesso fica em dourado. */
const DIAS_SEM_ACESSO = 14;

type Props = {
    clientes: ClienteLinha[];
    /** Dia de referência ('YYYY-MM-DD', no fuso da gestão). Padrão: hoje. */
    hoje?: string;
};

/** Paróquias com a atividade do período. Zero resolvidas aparece em vermelho; acesso antigo, em dourado. */
export function TabelaDeClientes({ clientes, hoje = hojeNoFuso() }: Props) {
    return (
        <div className="relative overflow-x-auto">
            <table className="w-full min-w-[640px] border-collapse text-sm tabular-nums">
                <caption className="sr-only">
                    Paróquias ordenadas por atividade no período
                </caption>
                <thead>
                    <tr className="text-left text-muted-foreground">
                        <th scope="col" className="px-5 py-2 font-semibold">
                            Paróquia
                        </th>
                        <th scope="col" className="px-2 py-2 font-semibold">
                            Configuração
                        </th>
                        <th
                            scope="col"
                            className="px-2 py-2 text-right font-semibold"
                        >
                            Reservas
                        </th>
                        <th
                            scope="col"
                            className="px-2 py-2 text-right font-semibold"
                        >
                            Conversas
                        </th>
                        <th
                            scope="col"
                            className="px-2 py-2 text-right font-semibold"
                        >
                            Resolvidas
                        </th>
                        <th
                            scope="col"
                            className="py-2 pr-5 pl-2 font-semibold"
                        >
                            Último acesso
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {clientes.length === 0 && (
                        <tr className="border-t border-border">
                            <td
                                colSpan={6}
                                className="px-5 py-4 text-muted-foreground"
                            >
                                Nenhuma paróquia encontrada.
                            </td>
                        </tr>
                    )}
                    {clientes.map((cliente) => {
                        const dias =
                            cliente.ultimo_acesso === null
                                ? null
                                : diasEntre(cliente.ultimo_acesso, hoje);
                        const acessoAntigo =
                            dias === null || dias >= DIAS_SEM_ACESSO;

                        return (
                            <tr
                                key={cliente.id}
                                className="border-t border-border transition-colors hover:bg-background"
                            >
                                <th
                                    scope="row"
                                    className="px-5 py-2.5 text-left font-semibold"
                                >
                                    <Link
                                        href={show(cliente.id)}
                                        className="rounded-sm text-foreground underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        {cliente.nome}
                                    </Link>
                                </th>
                                <td className="px-2 py-2.5">
                                    {cliente.configuracao
                                        ? `${cliente.configuracao.feitos} de ${cliente.configuracao.total}`
                                        : '—'}
                                </td>
                                <td className="px-2 py-2.5 text-right">
                                    {formatarNumero(cliente.reservas)}
                                </td>
                                <td className="px-2 py-2.5 text-right">
                                    {formatarNumero(cliente.conversas)}
                                </td>
                                <td
                                    className={
                                        cliente.resolvidas_pct === 0
                                            ? 'px-2 py-2.5 text-right font-semibold text-[#A33A2A]'
                                            : cliente.resolvidas_pct === null
                                              ? 'px-2 py-2.5 text-right text-muted-foreground'
                                              : 'px-2 py-2.5 text-right'
                                    }
                                >
                                    {formatarPct(cliente.resolvidas_pct)}
                                </td>
                                <td
                                    className={
                                        acessoAntigo
                                            ? 'py-2.5 pr-5 pl-2 font-semibold text-dourado'
                                            : 'py-2.5 pr-5 pl-2'
                                    }
                                >
                                    {cliente.ultimo_acesso === null
                                        ? 'nunca'
                                        : diaRelativo(
                                              cliente.ultimo_acesso,
                                              hoje,
                                          )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
