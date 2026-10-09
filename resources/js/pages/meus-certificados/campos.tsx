import { Head } from '@inertiajs/react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import CamposEditor from '@/components/campos/campos-editor';
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
    pode_editar: boolean;
    fontes: GruposFontes;
    pesos_das_fontes: PesosDasFontes;
};

export default function Campos({
    modelo,
    campos,
    pode_editar,
    fontes,
    pesos_das_fontes,
}: Props) {
    return (
        <>
            <Head title={`Campos: ${modelo.title}`} />
            <div className="p-4">
                <CabecalhoDaPagina
                    modelo={modelo.title}
                    titulo="Campos do certificado"
                    subtitulo="Defina onde cada campo de texto aparece sobre a imagem do modelo."
                />
                <CamposEditor
                    key={modelo.id}
                    modelo={modelo}
                    camposIniciais={campos}
                    fontes={fontes}
                    pesos={pesos_das_fontes}
                    somenteLeitura={!pode_editar}
                />
            </div>
        </>
    );
}

Campos.layout = {
    breadcrumbs: [
        {
            title: 'Meus certificados',
            href: index(),
        },
    ],
};
