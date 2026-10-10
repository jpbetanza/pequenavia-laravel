import { Head } from '@inertiajs/react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import { index as rota } from '@/routes/agendamentos/solicitacoes';

export default function Solicitacoes() {
    return (
        <>
            <Head title="Solicitações" />
            <CabecalhoDaPagina titulo="Solicitações" />
            <p className="text-muted-foreground">Em construção.</p>
        </>
    );
}

Solicitacoes.layout = {
    breadcrumbs: [{ title: 'Solicitações', href: rota() }],
};
