import { Head } from '@inertiajs/react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import { index as rota } from '@/routes/agendamentos/espacos';

export default function Espacos() {
    return (
        <>
            <Head title="Espaços" />
            <CabecalhoDaPagina titulo="Espaços" />
            <p className="text-muted-foreground">Em construção.</p>
        </>
    );
}

Espacos.layout = {
    breadcrumbs: [{ title: 'Espaços', href: rota() }],
};
