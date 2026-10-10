import { Head } from '@inertiajs/react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import { calendario as rota } from '@/routes/agendamentos';

export default function Calendario() {
    return (
        <>
            <Head title="Calendário" />
            <CabecalhoDaPagina titulo="Calendário" />
            <p className="text-muted-foreground">Em construção.</p>
        </>
    );
}

Calendario.layout = {
    breadcrumbs: [{ title: 'Calendário', href: rota() }],
};
