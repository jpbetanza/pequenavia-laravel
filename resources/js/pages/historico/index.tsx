import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import PainelDeDetalhes from '@/components/historico/painel-de-detalhes';
import ListaDoHistorico from '@/components/historico/lista-do-historico';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { index } from '@/routes/historico';
import type {
    FiltrosHistorico,
    Geracao,
    ModeloHistorico,
} from '@/types/certificados';

type Props = {
    geracoes: Geracao[];
    filtros: FiltrosHistorico;
    modelos: ModeloHistorico[];
};

const TODOS_OS_MODELOS = 'todos';
const DEBOUNCE_DA_BUSCA_MS = 300;
const LIMITE_DA_BUSCA = 100;

export default function Historico({ geracoes, filtros, modelos }: Props) {
    const [agora] = useState(() => new Date());
    const [busca, setBusca] = useState(filtros.busca);
    const [modelo, setModelo] = useState(filtros.modelo);
    const [selecionadaId, setSelecionadaId] = useState<string | null>(null);
    const [folhaAberta, setFolhaAberta] = useState(false);
    const buscaEmAndamento = useRef<{ cancel: () => void } | null>(null);

    const selecionada =
        geracoes.find((geracao) => geracao.id === selecionadaId) ??
        geracoes[0] ??
        null;

    const filtroAtivo = filtros.busca !== '' || filtros.modelo !== null;

    useEffect(() => {
        const termo = busca.trim();

        if (termo === filtros.busca && modelo === filtros.modelo) {
            return;
        }

        const temporizador = window.setTimeout(() => {
            // Cancela a visita anterior, para que uma resposta antiga não sobrescreva a nova.
            buscaEmAndamento.current?.cancel();
            router.get(
                index(),
                {
                    ...(termo !== '' ? { busca: termo } : {}),
                    ...(modelo !== null ? { modelo } : {}),
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onCancelToken: (token) => {
                        buscaEmAndamento.current = token;
                    },
                },
            );
        }, DEBOUNCE_DA_BUSCA_MS);

        return () => window.clearTimeout(temporizador);
    }, [busca, modelo, filtros.busca, filtros.modelo]);

    function limparFiltros() {
        setBusca('');
        setModelo(null);
    }

    function aoEscolherGeracao(geracao: Geracao) {
        setSelecionadaId(geracao.id);

        if (!window.matchMedia('(min-width: 1024px)').matches) {
            setFolhaAberta(true);
        }
    }

    return (
        <>
            <Head title="Histórico" />
            <div className="p-4 sm:p-6">
                <CabecalhoDaPagina
                    titulo="Histórico"
                    subtitulo="Cada certificado emitido, com os dados como foram usados."
                    acoes={
                        <div className="flex w-full flex-wrap items-center gap-2.5 sm:w-auto">
                            <div className="relative w-full sm:w-60">
                                <Search
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    type="search"
                                    aria-label="Buscar por nome"
                                    placeholder="Buscar por nome"
                                    value={busca}
                                    maxLength={LIMITE_DA_BUSCA}
                                    onChange={(evento) =>
                                        setBusca(evento.target.value)
                                    }
                                    className="h-11 rounded-full bg-card pl-10"
                                />
                            </div>
                            <Select
                                value={modelo ?? TODOS_OS_MODELOS}
                                onValueChange={(valor) =>
                                    setModelo(
                                        valor === TODOS_OS_MODELOS
                                            ? null
                                            : valor,
                                    )
                                }
                            >
                                <SelectTrigger
                                    aria-label="Filtrar por modelo"
                                    className="h-11 w-full rounded-full bg-card px-4 sm:w-auto"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={TODOS_OS_MODELOS}>
                                        Todos os modelos
                                    </SelectItem>
                                    {modelos.map((item) => (
                                        <SelectItem
                                            key={item.id}
                                            value={item.id}
                                        >
                                            {item.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    }
                />

                {geracoes.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-20 text-center">
                        <p className="text-lg font-medium text-muted-foreground">
                            {filtroAtivo
                                ? 'Nenhum certificado para essa busca'
                                : 'Nenhuma geração encontrada'}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {filtroAtivo
                                ? 'Tente outro nome ou mude o modelo.'
                                : 'As gerações de certificado aparecerão aqui depois que você gerar o primeiro certificado.'}
                        </p>
                        {filtroAtivo && (
                            <Button
                                variant="outline"
                                className="mt-2 min-h-11"
                                onClick={limparFiltros}
                            >
                                Limpar filtros
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                        <div className="min-w-0 flex-1">
                            <ListaDoHistorico
                                geracoes={geracoes}
                                agora={agora}
                                selecionadaId={selecionada?.id ?? null}
                                aoSelecionar={aoEscolherGeracao}
                            />
                        </div>

                        <aside
                            aria-label="Detalhes do certificado selecionado"
                            className="sticky top-22 hidden w-80 shrink-0 rounded-2xl border border-border bg-card p-6 lg:block"
                        >
                            {selecionada && (
                                <PainelDeDetalhes
                                    geracao={selecionada}
                                    agora={agora}
                                />
                            )}
                        </aside>
                    </div>
                )}
            </div>

            <Sheet open={folhaAberta} onOpenChange={setFolhaAberta}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-md lg:hidden">
                    <SheetHeader>
                        <SheetTitle>Detalhes</SheetTitle>
                        <SheetDescription>
                            Dados do certificado selecionado.
                        </SheetDescription>
                    </SheetHeader>
                    {selecionada && (
                        <div className="px-4 pb-6">
                            <PainelDeDetalhes
                                geracao={selecionada}
                                agora={agora}
                            />
                        </div>
                    )}
                </SheetContent>
            </Sheet>
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
