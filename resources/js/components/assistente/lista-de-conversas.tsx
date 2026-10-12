import { ArrowLeft, Search, SquarePen, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    destroy as excluirConversa,
    index as listarConversas,
} from '@/actions/App/Http/Controllers/Assistente/ConversaController';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { buscarJson } from '@/lib/assistente/enviar';
import { DICA_AGUARDE_RESPOSTA } from '@/lib/assistente/painel';
import { agruparConversas, filtrarConversas } from '@/lib/assistente/lista';
import { enviarJson, mensagemDoErro } from '@/lib/http';
import { tempoRelativo } from '@/lib/tempo';
import { cn } from '@/lib/utils';
import type { ResumoDeConversa } from '@/types/assistente';

type Props = {
    /** A lista fica montada o tempo todo; recarrega sempre que volta a ficar visível. */
    visivel: boolean;
    conversaAtualId: string | null;
    /** Resposta em andamento: não dá para trocar de conversa nem criar outra sem cortar o stream. */
    enviando: boolean;
    aoAbrir: (id: string) => void;
    aoNovaConversa: () => void;
    aoVoltar: () => void;
    aoFechar: () => void;
    aoExcluir: (id: string) => void;
};

export function ListaDeConversas({
    visivel,
    conversaAtualId,
    enviando,
    aoAbrir,
    aoNovaConversa,
    aoVoltar,
    aoFechar,
    aoExcluir,
}: Props) {
    const [conversas, setConversas] = useState<ResumoDeConversa[] | null>(null);
    const [falhou, setFalhou] = useState(false);
    const [busca, setBusca] = useState('');
    const [agora, setAgora] = useState(() => new Date().toISOString());
    const [paraExcluir, setParaExcluir] = useState<ResumoDeConversa | null>(
        null,
    );
    const [excluindo, setExcluindo] = useState(false);

    useEffect(() => {
        if (!visivel) {
            return;
        }

        const controle = new AbortController();

        buscarJson<{ conversas: ResumoDeConversa[] }>(
            listarConversas.url(),
            controle.signal,
        )
            .then((dados) => {
                // "agora" vem da hora da busca, para o tempo relativo não congelar na montagem.
                setFalhou(false);
                setAgora(new Date().toISOString());
                setConversas(dados.conversas);
            })
            .catch((erro: unknown) => {
                if (!controle.signal.aborted) {
                    setFalhou(true);
                    toast.error(mensagemDoErro(erro));
                }
            });

        return () => controle.abort();
    }, [visivel]);

    async function excluir(conversa: ResumoDeConversa) {
        setExcluindo(true);

        try {
            await enviarJson(
                'DELETE',
                excluirConversa.url({ conversa: conversa.id }),
            );
            setConversas(
                (atuais) =>
                    atuais?.filter((item) => item.id !== conversa.id) ?? null,
            );
            setParaExcluir(null);
            aoExcluir(conversa.id);
        } catch (erro) {
            toast.error(mensagemDoErro(erro));
        } finally {
            setExcluindo(false);
        }
    }

    const grupos = agruparConversas(
        filtrarConversas(conversas ?? [], busca),
        agora,
    );

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <header className="flex items-center gap-2 border-b py-3 pr-3 pl-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Voltar para a conversa"
                    onClick={aoVoltar}
                    className="size-11 text-muted-foreground"
                >
                    <ArrowLeft aria-hidden="true" />
                </Button>
                <span className="flex-1 font-serif text-2xl leading-tight font-semibold">
                    Conversas
                </span>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Fechar assistente"
                    onClick={aoFechar}
                    className="size-11 text-muted-foreground"
                >
                    <X aria-hidden="true" />
                </Button>
            </header>

            <div className="flex flex-col gap-2.5 p-4">
                <Button
                    type="button"
                    onClick={aoNovaConversa}
                    disabled={enviando}
                    title={enviando ? DICA_AGUARDE_RESPOSTA : undefined}
                    className="min-h-11 w-full rounded-xl"
                >
                    <SquarePen aria-hidden="true" />
                    Nova conversa
                </Button>
                <div className="relative">
                    <label htmlFor="buscar-conversa" className="sr-only">
                        Buscar nas conversas
                    </label>
                    <Search
                        aria-hidden="true"
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="buscar-conversa"
                        type="search"
                        placeholder="Buscar nas conversas"
                        value={busca}
                        onChange={(evento) => setBusca(evento.target.value)}
                        className="h-11 pl-9"
                    />
                </div>
            </div>

            <nav
                aria-label="Conversas anteriores"
                className="flex min-h-0 flex-1 flex-col overflow-y-auto px-2 pb-4"
            >
                {conversas === null && !falhou && (
                    <p className="flex items-center gap-2 px-2 py-3 text-sm text-muted-foreground">
                        <Spinner />
                        Carregando conversas…
                    </p>
                )}

                {falhou && (
                    <p className="px-2 py-3 text-sm text-muted-foreground">
                        Não consegui carregar as conversas.
                    </p>
                )}

                {conversas !== null && conversas.length === 0 && (
                    <p className="px-2 py-3 text-sm text-muted-foreground">
                        Nenhuma conversa ainda.
                    </p>
                )}

                {conversas !== null &&
                    conversas.length > 0 &&
                    grupos.length === 0 && (
                        <p className="px-2 py-3 text-sm text-muted-foreground">
                            Nenhuma conversa encontrada.
                        </p>
                    )}

                {grupos.map((grupo) => (
                    <div key={grupo.rotulo} className="flex flex-col">
                        <p className="px-2.5 pt-3 pb-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {grupo.rotulo}
                        </p>
                        <ul className="flex flex-col gap-0.5">
                            {grupo.conversas.map((conversa) => {
                                const atual = conversa.id === conversaAtualId;
                                const quando = tempoRelativo(
                                    conversa.ultima_mensagem_em,
                                    new Date(agora),
                                );

                                return (
                                    <li
                                        key={conversa.id}
                                        className="flex items-center gap-1"
                                    >
                                        <button
                                            type="button"
                                            aria-current={
                                                atual ? 'true' : undefined
                                            }
                                            disabled={enviando}
                                            title={
                                                enviando
                                                    ? DICA_AGUARDE_RESPOSTA
                                                    : undefined
                                            }
                                            onClick={() => aoAbrir(conversa.id)}
                                            className={cn(
                                                'flex min-h-11 min-w-0 flex-1 flex-col gap-0.5 rounded-xl px-3 py-2 text-left hover:bg-muted/60',
                                                atual && 'bg-primary/10',
                                            )}
                                        >
                                            <span className="flex items-baseline justify-between gap-2">
                                                <span className="truncate font-semibold">
                                                    {conversa.titulo}
                                                </span>
                                                {quando !== null && (
                                                    <span className="shrink-0 text-xs text-muted-foreground">
                                                        {quando}
                                                    </span>
                                                )}
                                            </span>
                                            {conversa.previa !== null && (
                                                <span className="truncate text-sm text-muted-foreground">
                                                    {conversa.previa}
                                                </span>
                                            )}
                                        </button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Excluir conversa “${conversa.titulo}”`}
                                            // Excluir a conversa que está respondendo faria o painel trocar de conversa no meio do stream.
                                            disabled={
                                                enviando &&
                                                conversa.id === conversaAtualId
                                            }
                                            title={
                                                enviando &&
                                                conversa.id === conversaAtualId
                                                    ? DICA_AGUARDE_RESPOSTA
                                                    : undefined
                                            }
                                            onClick={() =>
                                                setParaExcluir(conversa)
                                            }
                                            className="size-11 shrink-0 text-muted-foreground"
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>

            <AlertDialog
                open={paraExcluir !== null}
                onOpenChange={(aberto) => {
                    if (!aberto && !excluindo) {
                        setParaExcluir(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Excluir esta conversa?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            “{paraExcluir?.titulo}” some da sua lista. Isso não
                            pode ser desfeito.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={excluindo}>
                            Cancelar
                        </AlertDialogCancel>
                        <AlertDialogAction
                            disabled={excluindo}
                            onClick={(evento) => {
                                // Fecha só depois que o servidor confirma a exclusão.
                                evento.preventDefault();

                                if (paraExcluir !== null) {
                                    void excluir(paraExcluir);
                                }
                            }}
                        >
                            {excluindo && <Spinner />}
                            Excluir
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
