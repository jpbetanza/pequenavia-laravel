import { Head } from '@inertiajs/react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import { index as rota } from '@/routes/agendamentos/equipamentos';

export default function Equipamentos() {
    return (
        <>
            <Head title="Equipamentos" />
            <CabecalhoDaPagina titulo="Equipamentos" />
            <p className="text-muted-foreground">Em construção.</p>
        </>
    );
}

Equipamentos.layout = {
    breadcrumbs: [{ title: 'Equipamentos', href: rota() }],
};
