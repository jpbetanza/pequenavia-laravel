import { Head } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import CartaoDeModelo from '@/components/modelos/cartao-de-modelo';
import DialogoAlterarModelo from '@/components/modelos/dialogo-alterar-modelo';
import DialogoNovoModelo from '@/components/modelos/dialogo-novo-modelo';
import DialogoOpcoesDoModelo, {
    ConfirmacaoDeExclusao,
} from '@/components/modelos/dialogo-opcoes-do-modelo';
import SecaoCompartilhados from '@/components/modelos/secao-compartilhados';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { filtrarPorTitulo } from '@/lib/busca';
import { index } from '@/routes/meus-certificados';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    modelos: ModeloResumo[];
    compartilhados: ModeloResumo[];
};

type OpcoesAbertas = {
    id: string;
    compartilhando: boolean;
};

export default function MeusCertificados({ modelos, compartilhados }: Props) {
    const [busca, setBusca] = useState('');
    const [novoAberto, setNovoAberto] = useState(false);
    const [opcoes, setOpcoes] = useState<OpcoesAbertas | null>(null);
    const [alterarDe, setAlterarDe] = useState<string | null>(null);
    const [excluirDe, setExcluirDe] = useState<string | null>(null);

    const buscando = busca.trim() !== '';
    const meusFiltrados = filtrarPorTitulo(modelos, busca);
    const compartilhadosFiltrados = filtrarPorTitulo(compartilhados, busca);

    const modeloDasOpcoes =
        modelos.find((modelo) => modelo.id === opcoes?.id) ?? null;
    const modeloAlterado =
        modelos.find((modelo) => modelo.id === alterarDe) ?? null;
    const modeloExcluido =
        modelos.find((modelo) => modelo.id === excluirDe) ?? null;

    return (
        <>
            <Head title="Modelos" />
            <div className="flex flex-col gap-10 p-4">
                <CabecalhoDaPagina
                    titulo="Modelos"
                    subtitulo="A arte de cada certificado e onde entram os dados."
                    acoes={
                        <>
                            <div className="relative w-full sm:w-64">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    type="search"
                                    aria-label="Buscar modelos"
                                    placeholder="Buscar modelo"
                                    value={busca}
                                    onChange={(evento) =>
                                        setBusca(evento.target.value)
                                    }
                                    className="h-11 rounded-full bg-card pl-10"
                                />
                            </div>
                            <Button
                                className="h-11 rounded-full px-5"
                                onClick={() => setNovoAberto(true)}
                            >
                                <Plus />
                                Novo modelo
                            </Button>
                        </>
                    }
                />

                <section
                    aria-labelledby="meus-modelos"
                    className="flex flex-col gap-4"
                >
                    <h2
                        id="meus-modelos"
                        className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                    >
                        Meus modelos · {meusFiltrados.length}
                    </h2>

                    {meusFiltrados.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-input px-5 py-10 text-center">
                            <p className="text-muted-foreground">
                                {buscando
                                    ? 'Nenhum modelo corresponde à busca.'
                                    : 'Você ainda não tem modelos. Crie o primeiro para começar.'}
                            </p>
                            {!buscando && (
                                <Button onClick={() => setNovoAberto(true)}>
                                    <Plus />
                                    Novo modelo
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div className="grid grid-cols-[repeat(auto-fill,minmax(min(100%,300px),1fr))] gap-6">
                            {meusFiltrados.map((modelo) => (
                                <CartaoDeModelo
                                    key={modelo.id}
                                    modelo={modelo}
                                    aoAlterar={() => setAlterarDe(modelo.id)}
                                    aoCompartilhar={() =>
                                        setOpcoes({
                                            id: modelo.id,
                                            compartilhando: true,
                                        })
                                    }
                                    aoExcluir={() => setExcluirDe(modelo.id)}
                                />
                            ))}
                        </div>
                    )}
                </section>

                <SecaoCompartilhados
                    itens={compartilhadosFiltrados}
                    buscando={buscando}
                />
            </div>

            <DialogoNovoModelo
                aberto={novoAberto}
                aoFechar={() => setNovoAberto(false)}
            />
            <DialogoOpcoesDoModelo
                modelo={modeloDasOpcoes}
                compartilhando={opcoes?.compartilhando ?? false}
                aoAlternarCompartilhamento={() =>
                    setOpcoes(
                        (atual) =>
                            atual && {
                                ...atual,
                                compartilhando: !atual.compartilhando,
                            },
                    )
                }
                aoFechar={() => setOpcoes(null)}
                aoAlterar={setAlterarDe}
            />
            <DialogoAlterarModelo
                modelo={modeloAlterado}
                aoFechar={() => setAlterarDe(null)}
            />
            {modeloExcluido && (
                <ConfirmacaoDeExclusao
                    modelo={modeloExcluido}
                    aberta
                    aoAbertaChange={(aberta) => !aberta && setExcluirDe(null)}
                    aoExcluido={() => setExcluirDe(null)}
                />
            )}
        </>
    );
}

MeusCertificados.layout = {
    breadcrumbs: [
        {
            title: 'Modelos',
            href: index(),
        },
    ],
};
