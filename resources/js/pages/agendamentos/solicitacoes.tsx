import { Head, Link } from '@inertiajs/react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import CartaoDeSolicitacao from '@/components/agendamentos/cartao-de-solicitacao';
import LinkDoFormulario from '@/components/agendamentos/link-do-formulario';
import { cn } from '@/lib/utils';
import { index as rota } from '@/routes/agendamentos/solicitacoes';
import type {
    PropsDaSolicitacoes,
    StatusDaSolicitacao,
} from '@/types/agendamentos';

const ABAS: { valor: StatusDaSolicitacao; rotulo: string }[] = [
    { valor: 'pendente', rotulo: 'Pendentes' },
    { valor: 'aprovada', rotulo: 'Aprovadas' },
    { valor: 'recusada', rotulo: 'Recusadas' },
];

const VAZIO_POR_ABA: Record<StatusDaSolicitacao, string> = {
    pendente:
        'Nenhuma solicitação pendente. Quando alguém pedir uma sala pelo formulário, o pedido aparece aqui.',
    aprovada: 'Nenhuma solicitação aprovada ainda.',
    recusada: 'Nenhuma solicitação recusada.',
};

export default function Solicitacoes({
    link_do_formulario,
    contagens,
    status,
    solicitacoes,
    espacos,
    equipamentos,
}: PropsDaSolicitacoes) {
    return (
        <>
            <Head title="Solicitações" />
            <div className="flex flex-col gap-10 p-4">
                <CabecalhoDaPagina
                    titulo="Solicitações"
                    subtitulo="Pedidos feitos pelo formulário aberto. Confira, ajuste o que precisar e decida. Só entra no calendário o que você aprovar."
                />

                <div className="flex flex-col gap-6">
                    <LinkDoFormulario endereco={link_do_formulario} />

                    <nav
                        aria-label="Situação das solicitações"
                        className="flex gap-1 overflow-x-auto border-b"
                    >
                        {ABAS.map((aba) => {
                            const ativa = aba.valor === status;

                            return (
                                <Link
                                    key={aba.valor}
                                    href={rota({
                                        query: { status: aba.valor },
                                    })}
                                    preserveScroll
                                    aria-current={ativa ? 'page' : undefined}
                                    className={cn(
                                        '-mb-px inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-4 text-sm font-medium transition-colors',
                                        ativa
                                            ? 'border-primary text-primary'
                                            : 'border-transparent text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {aba.rotulo}
                                    <span
                                        className={cn(
                                            'rounded-full px-2 text-xs tabular-nums',
                                            ativa
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-secondary text-secondary-foreground',
                                        )}
                                    >
                                        {contagens[aba.valor]}
                                    </span>
                                </Link>
                            );
                        })}
                    </nav>

                    {solicitacoes.length === 0 ? (
                        <p className="rounded-2xl border bg-card p-6 text-muted-foreground">
                            {VAZIO_POR_ABA[status]}
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-4">
                            {solicitacoes.map((cartao) => (
                                <li key={JSON.stringify(cartao)}>
                                    <CartaoDeSolicitacao
                                        cartao={cartao}
                                        espacos={espacos}
                                        equipamentos={equipamentos}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}

Solicitacoes.layout = {
    breadcrumbs: [{ title: 'Solicitações', href: rota() }],
};
