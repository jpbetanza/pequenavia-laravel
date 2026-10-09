import { Head } from '@inertiajs/react';
import { index } from '@/routes/meus-certificados';

export type CampoCertificado = {
    id: string;
    modelo_id: string;
    nome: string;
    label: string | null;
    x: number;
    y: number;
    cor: string;
    tamanho_fonte: number;
    fonte_familia: string;
    peso_fonte: number;
    espacamento_letras: number;
    centralizado: boolean;
};

/** Grupo => (família exibida => pasta dos arquivos de fonte). */
export type GruposFontes = Record<string, Record<string, string>>;

type Props = {
    modelo: { id: string; title: string; imagem_url: string };
    campos: CampoCertificado[];
    pode_editar: boolean;
    fontes: GruposFontes;
};

export default function Campos({ modelo, campos, pode_editar }: Props) {
    return (
        <>
            <Head title={`Campos — ${modelo.title}`} />
            <div className="space-y-4 p-4">
                <h1 className="text-xl font-semibold">{modelo.title}</h1>
                <img
                    src={modelo.imagem_url}
                    alt={modelo.title}
                    className="max-w-full"
                />
                <p>
                    {pode_editar
                        ? 'Você pode editar os campos.'
                        : 'Somente leitura.'}
                </p>
                <ul>
                    {campos.map((campo) => (
                        <li key={campo.id}>{campo.label || campo.nome}</li>
                    ))}
                </ul>
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
