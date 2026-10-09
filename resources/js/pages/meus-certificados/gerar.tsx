import { Head } from '@inertiajs/react';

type Props = {
    modelo: { id: string; title: string; imagem_url: string };
    campos: { id: string; nome: string }[];
};

export default function Gerar({ modelo }: Props) {
    return (
        <>
            <Head title={`Gerar — ${modelo.title}`} />
            <div className="space-y-4 p-4">
                <h1 className="text-xl font-semibold">{modelo.title}</h1>
            </div>
        </>
    );
}
