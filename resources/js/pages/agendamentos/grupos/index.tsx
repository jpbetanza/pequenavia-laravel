import { Head } from '@inertiajs/react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import { index as rota } from '@/routes/agendamentos/grupos';

export default function Grupos() {
    return (
        <>
            <Head title="Grupos e pastorais" />
            <CabecalhoDaPagina titulo="Grupos e pastorais" />
            <p className="text-muted-foreground">Em construção.</p>
        </>
    );
}

Grupos.layout = {
    breadcrumbs: [{ title: 'Grupos e pastorais', href: rota() }],
};
