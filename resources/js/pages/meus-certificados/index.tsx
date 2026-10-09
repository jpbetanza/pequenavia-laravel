import { Head } from '@inertiajs/react';
import { index } from '@/routes/meus-certificados';

export type CompartilhamentoResumo = {
    id: string;
    email: string;
    pode_editar: boolean;
};

export type ModeloResumo = {
    id: string;
    title: string;
    description: string | null;
    imagem_url: string;
    dono_nome: string;
    eh_dono: boolean;
    pode_editar: boolean;
    compartilhamentos: CompartilhamentoResumo[];
};

type Props = {
    modelos: ModeloResumo[];
    compartilhados: ModeloResumo[];
};

export default function MeusCertificados({ modelos, compartilhados }: Props) {
    return (
        <>
            <Head title="Meus certificados" />
            <div className="space-y-6 p-4">
                <section>
                    <h1 className="text-xl font-semibold">Meus modelos</h1>
                    <ul>
                        {modelos.map((modelo) => (
                            <li key={modelo.id}>{modelo.title}</li>
                        ))}
                    </ul>
                </section>
                <section>
                    <h2 className="text-lg font-semibold">Compartilhados comigo</h2>
                    <ul>
                        {compartilhados.map((modelo) => (
                            <li key={modelo.id}>
                                {modelo.title} — {modelo.dono_nome}
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

MeusCertificados.layout = {
    breadcrumbs: [
        {
            title: 'Meus certificados',
            href: index(),
        },
    ],
};
