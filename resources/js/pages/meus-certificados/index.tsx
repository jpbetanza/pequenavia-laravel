import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import CartaoDeModelo from '@/components/modelos/cartao-de-modelo';
import DialogoAlterarModelo from '@/components/modelos/dialogo-alterar-modelo';
import DialogoNovoModelo from '@/components/modelos/dialogo-novo-modelo';
import DialogoOpcoesDoModelo from '@/components/modelos/dialogo-opcoes-do-modelo';
import SecaoCompartilhados from '@/components/modelos/secao-compartilhados';
import { index } from '@/routes/meus-certificados';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    modelos: ModeloResumo[];
    compartilhados: ModeloResumo[];
};

export default function MeusCertificados({ modelos, compartilhados }: Props) {
    const [novoAberto, setNovoAberto] = useState(false);
    const [opcoesDe, setOpcoesDe] = useState<string | null>(null);
    const [alterarDe, setAlterarDe] = useState<string | null>(null);

    return (
        <>
            <Head title="Meus certificados" />
            <div className="p-4">
                <CabecalhoDaPagina
                    titulo="Meus certificados"
                    subtitulo="Gerencie seus modelos de certificado"
                />

                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    {modelos.map((modelo) => (
                        <CartaoDeModelo
                            key={modelo.id}
                            modelo={modelo}
                            aoClicar={() => setOpcoesDe(modelo.id)}
                        />
                    ))}

                    <button
                        type="button"
                        onClick={() => setNovoAberto(true)}
                        className="flex min-h-[200px] cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed p-6 text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                    >
                        <Plus className="size-8" />
                        <span className="text-sm font-medium">
                            Adicionar novo modelo
                        </span>
                    </button>
                </div>

                <SecaoCompartilhados itens={compartilhados} />
            </div>

            <DialogoNovoModelo
                aberto={novoAberto}
                aoFechar={() => setNovoAberto(false)}
            />
            <DialogoOpcoesDoModelo
                modelo={
                    modelos.find((modelo) => modelo.id === opcoesDe) ?? null
                }
                aoFechar={() => setOpcoesDe(null)}
                aoAlterar={setAlterarDe}
            />
            <DialogoAlterarModelo
                modelo={
                    modelos.find((modelo) => modelo.id === alterarDe) ?? null
                }
                aoFechar={() => setAlterarDe(null)}
            />
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
