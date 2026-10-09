import { Head } from '@inertiajs/react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import GerarCertificados from '@/components/gerar/gerar-certificados';
import { index } from '@/routes/meus-certificados';
import type {
    Campo,
    GruposFontes,
    ModeloBasico,
    PesosDasFontes,
} from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    campos: Campo[];
    fontes: GruposFontes;
    pesos_das_fontes: PesosDasFontes;
};

export default function Gerar({
    modelo,
    campos,
    fontes,
    pesos_das_fontes,
}: Props) {
    return (
        <>
            <Head title={`Gerar certificados: ${modelo.title}`} />
            <div className="p-4">
                <CabecalhoDaPagina
                    modelo={modelo.title}
                    titulo="Gerar certificados"
                />
                <GerarCertificados
                    key={modelo.id}
                    modelo={modelo}
                    campos={campos}
                    fontes={fontes}
                    pesos={pesos_das_fontes}
                />
            </div>
        </>
    );
}

Gerar.layout = {
    breadcrumbs: [
        {
            title: 'Meus certificados',
            href: index(),
        },
    ],
};
