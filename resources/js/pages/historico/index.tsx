import { Head } from '@inertiajs/react';
import { index } from '@/routes/historico';

export type Geracao = {
    id: string;
    modelo_id: string | null;
    modelo_title: string;
    campos_valores: Record<string, string>;
    created_at: string;
};

export default function Historico({ geracoes }: { geracoes: Geracao[] }) {
    return (
        <>
            <Head title="Histórico" />
            <div className="p-4">
                <h1 className="text-xl font-semibold">Histórico</h1>
                <ul>
                    {geracoes.map((geracao) => (
                        <li key={geracao.id}>
                            {geracao.modelo_title} —{' '}
                            {new Date(geracao.created_at).toLocaleString(
                                'pt-BR',
                            )}
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

Historico.layout = {
    breadcrumbs: [
        {
            title: 'Histórico',
            href: index(),
        },
    ],
};
