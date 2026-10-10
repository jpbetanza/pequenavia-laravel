import { Head } from '@inertiajs/react';
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
    valores_iniciais: Record<string, string> | null;
};

export default function Gerar({
    modelo,
    campos,
    fontes,
    pesos_das_fontes,
    valores_iniciais,
}: Props) {
    return (
        <>
            <Head title={`Gerar certificados: ${modelo.title}`} />
            <div className="p-4">
                <GerarCertificados
                    key={modelo.id}
                    modelo={modelo}
                    campos={campos}
                    fontes={fontes}
                    pesos={pesos_das_fontes}
                    valoresIniciais={valores_iniciais}
                />
            </div>
        </>
    );
}

Gerar.layout = {
    breadcrumbs: [
        {
            title: 'Modelos',
            href: index(),
        },
    ],
};
