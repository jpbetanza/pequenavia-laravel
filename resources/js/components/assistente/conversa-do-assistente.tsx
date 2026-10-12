import { List, SquarePen, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    show as mostrarConversa,
    store as criarConversa,
} from '@/actions/App/Http/Controllers/Assistente/ConversaController';
import { store as enviarAoAssistente } from '@/actions/App/Http/Controllers/Assistente/MensagemController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { buscarJson, enviarMensagem } from '@/lib/assistente/enviar';
import { deveDescartarConversa } from '@/lib/assistente/conversa-salva';
import {
    aplicarEvento,
    iniciarTroca,
    MENSAGEM_DE_FALHA,
    substituirCartaoDeAcao,
} from '@/lib/assistente/eventos';
import { DICA_AGUARDE_RESPOSTA } from '@/lib/assistente/painel';
import { enviarJson, mensagemDoErro } from '@/lib/http';
import type {
    AcaoProposta,
    Cartao as TipoDeCartao,
    MensagemDoPainel,
} from '@/types/assistente';
import { CampoDeMensagem } from './campo-de-mensagem';
import { Cartao } from './cartoes/cartao';
import { LinhaDeStatus } from './linha-de-status';

const SUGESTOES = [
    'O que tem amanhã?',
    'Solicitações pendentes',
    'Certificados emitidos este mês',
];

type ConversaCarregada = {
    conversa: { id: string; titulo: string };
    mensagens: {
        id: string;
        papel: 'usuario' | 'assistente';
        conteudo: string;
        cartoes: TipoDeCartao[];
        erro: boolean;
        criada_em: string;
    }[];
};

type Props = {
    /** Conversa a abrir ao montar. Muda só quando o painel troca de conversa (que remonta este componente). */
    conversaIdInicial: string | null;
    aoConversaCriada: (id: string) => void;
    /** Conversa salva não existe mais: o painel esquece a referência. */
    aoConversaPerdida: () => void;
    aoVerConversas: () => void;
    aoNovaConversa: () => void;
    aoFechar: () => void;
    /** Avisa o painel quando a resposta começa e quando termina, para a lista desabilitar a troca de conversa. */
    aoMudarEnviando: (enviando: boolean) => void;
    /** Pergunta a enviar assim que esta conversa (nova) monta. */
    perguntaInicial?: string | null;
    aoPerguntaEnviada?: () => void;
};

/** Marca como concluída a resposta que ficou pendente (parada pelo usuário ou stream que terminou sem `fim`). */
function encerrarPendente(mensagens: MensagemDoPainel[]): MensagemDoPainel[] {
    return mensagens.map((mensagem) =>
        mensagem.papel === 'assistente' && mensagem.pendente
            ? { ...mensagem, pendente: false, status: null }
            : mensagem,
    );
}

function paraPainel(
    mensagem: ConversaCarregada['mensagens'][number],
): MensagemDoPainel {
    return {
        id: mensagem.id,
        papel: mensagem.papel,
        conteudo: mensagem.conteudo,
        cartoes: mensagem.cartoes,
        erro: mensagem.erro,
        status: null,
        pendente: false,
    };
}

export function ConversaDoAssistente({
    conversaIdInicial,
    aoConversaCriada,
    aoConversaPerdida,
    aoVerConversas,
    aoNovaConversa,
    aoFechar,
    aoMudarEnviando,
    perguntaInicial,
    aoPerguntaEnviada,
}: Props) {
    // A abertura usa só o valor do primeiro render. Criar a conversa no primeiro envio não deve recarregá-la.
    const [inicial] = useState(conversaIdInicial);
    const [conversaId, setConversaId] = useState(inicial);
    const [titulo, setTitulo] = useState<string | null>(null);
    const [mensagens, setMensagens] = useState<MensagemDoPainel[]>([]);
    const [carregando, setCarregando] = useState(inicial !== null);
    const [enviando, setEnviando] = useState(false);
    const [texto, setTexto] = useState('');
    const registroRef = useRef<HTMLDivElement>(null);
    const emAndamento = useRef(false);
    const controleRef = useRef<AbortController | null>(null);

    useEffect(() => {
        if (inicial === null) {
            return;
        }

        const controle = new AbortController();

        buscarJson<ConversaCarregada>(
            mostrarConversa.url({ conversa: inicial }),
            controle.signal,
        )
            .then((dados) => {
                setTitulo(dados.conversa.titulo);
                setMensagens(dados.mensagens.map(paraPainel));
            })
            .catch((erro: unknown) => {
                if (controle.signal.aborted) {
                    return;
                }

                if (deveDescartarConversa(erro)) {
                    // Conversa apagada ou de outro usuário: começa uma conversa nova, sem aviso.
                    setConversaId(null);
                    setTitulo(null);
                    setMensagens([]);
                    aoConversaPerdida();

                    return;
                }

                toast.error(mensagemDoErro(erro));
            })
            .finally(() => {
                if (!controle.signal.aborted) {
                    setCarregando(false);
                }
            });

        return () => controle.abort();
    }, [inicial]);

    useEffect(() => {
        const registro = registroRef.current;

        if (registro !== null) {
            registro.scrollTop = registro.scrollHeight;
        }
    }, [mensagens]);

    useEffect(
        () => () => {
            controleRef.current?.abort();
        },
        [],
    );

    // Pergunta vinda de fora: envia uma vez, ao montar. Adia para depois do ciclo de montagem: no StrictMode
    // o primeiro agendamento é cancelado pelo cleanup e só o segundo envia, sem ser abortado.
    useEffect(() => {
        if (!perguntaInicial) {
            return;
        }

        const agendado = window.setTimeout(() => {
            aoPerguntaEnviada?.();
            void enviar(perguntaInicial);
        }, 0);

        return () => window.clearTimeout(agendado);
        // Só ao montar a conversa nova: a pergunta inicial não muda depois disso.
    }, []);

    async function enviar(pergunta: string) {
        const limpa = pergunta.trim();

        if (limpa === '' || emAndamento.current) {
            return;
        }

        emAndamento.current = true;
        setEnviando(true);
        aoMudarEnviando(true);

        const controle = new AbortController();
        const ids = {
            usuario: crypto.randomUUID(),
            assistente: crypto.randomUUID(),
        };
        let recebeuEvento = false;
        let desfecho = false;
        controleRef.current = controle;
        setMensagens((atuais) => iniciarTroca(atuais, limpa, ids));

        try {
            let id = conversaId;

            if (id === null) {
                const criada = await enviarJson<{
                    conversa: { id: string; titulo: string };
                }>('POST', criarConversa.url(), undefined, controle.signal);

                if (criada === null) {
                    throw new Error('Não foi possível criar a conversa.');
                }

                id = criada.conversa.id;
                setConversaId(id);
                aoConversaCriada(id);
            }

            await enviarMensagem(
                enviarAoAssistente.url(id),
                limpa,
                (evento) => {
                    recebeuEvento = true;

                    if (evento.tipo === 'fim' || evento.tipo === 'erro') {
                        desfecho = true;
                    }

                    if (evento.tipo === 'fim') {
                        setTitulo(evento.conversa.titulo);
                    }

                    setMensagens((atuais) => aplicarEvento(atuais, evento));
                },
                controle.signal,
            );

            if (!desfecho) {
                // O stream acabou sem `fim` nem `erro`: a falha aparece na resposta, com o botão de tentar de novo.
                setMensagens((atuais) =>
                    aplicarEvento(atuais, {
                        tipo: 'erro',
                        mensagem: MENSAGEM_DE_FALHA,
                    }),
                );
            }
        } catch (erro) {
            if (controle.signal.aborted && recebeuEvento) {
                setMensagens(encerrarPendente);
            } else if (recebeuEvento) {
                const mensagem = mensagemDoErro(erro);

                setMensagens((atuais) =>
                    aplicarEvento(atuais, { tipo: 'erro', mensagem }),
                );
            } else {
                // Nada chegou: a troca some e a pergunta volta para o campo.
                setMensagens((atuais) =>
                    atuais.filter(
                        (mensagem) =>
                            mensagem.id !== ids.usuario &&
                            mensagem.id !== ids.assistente,
                    ),
                );
                setTexto((atual) => (atual === '' ? limpa : atual));

                if (!controle.signal.aborted) {
                    toast.error(mensagemDoErro(erro));
                }
            }
        } finally {
            emAndamento.current = false;
            controleRef.current = null;
            setEnviando(false);
            aoMudarEnviando(false);
        }
    }

    function enviarDoCampo() {
        const pergunta = texto;

        setTexto('');
        void enviar(pergunta);
    }

    function tentarDeNovo() {
        const indice = mensagens.findLastIndex(
            (mensagem) => mensagem.papel === 'usuario',
        );

        if (indice === -1) {
            return;
        }

        const pergunta = mensagens[indice].conteudo;

        setMensagens((atuais) => atuais.slice(0, indice));
        void enviar(pergunta);
    }

    function atualizarAcao(acao: AcaoProposta) {
        setMensagens((atuais) => substituirCartaoDeAcao(atuais, acao));
    }

    const vazia = mensagens.length === 0;
    const ultimaId = mensagens.at(-1)?.id;

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <header className="flex items-center gap-2 border-b py-3 pr-3 pl-5">
                <div className="flex min-w-0 flex-1 flex-col">
                    <span className="font-serif text-2xl leading-tight font-semibold">
                        Assistente
                    </span>
                    {titulo !== null && (
                        <span className="truncate text-xs text-muted-foreground">
                            {titulo}
                        </span>
                    )}
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Ver conversas"
                    onClick={aoVerConversas}
                    className="size-11 text-muted-foreground"
                >
                    <List aria-hidden="true" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Nova conversa"
                    onClick={aoNovaConversa}
                    disabled={enviando}
                    title={enviando ? DICA_AGUARDE_RESPOSTA : undefined}
                    className="size-11 text-muted-foreground"
                >
                    <SquarePen aria-hidden="true" />
                </Button>
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

            <div
                ref={registroRef}
                role="log"
                aria-live="polite"
                aria-busy={enviando}
                className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-4 py-5"
            >
                {carregando && (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner />
                        Carregando a conversa…
                    </p>
                )}

                {!carregando && vazia && (
                    <p className="my-auto text-center text-muted-foreground">
                        Pergunte sobre a agenda ou os certificados.
                    </p>
                )}

                {mensagens.map((mensagem) =>
                    mensagem.papel === 'usuario' ? (
                        <div key={mensagem.id} className="flex justify-end">
                            <p className="max-w-[85%] rounded-2xl rounded-br-sm bg-primary px-3.5 py-2.5 whitespace-pre-wrap text-primary-foreground">
                                {mensagem.conteudo}
                            </p>
                        </div>
                    ) : (
                        <div
                            key={mensagem.id}
                            className="flex flex-col gap-2.5"
                        >
                            {mensagem.erro ? (
                                <div className="flex flex-col items-start gap-2">
                                    <p className="whitespace-pre-wrap text-destructive">
                                        {mensagem.conteudo}
                                    </p>
                                    {mensagem.id === ultimaId && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={enviando}
                                            onClick={tentarDeNovo}
                                            className="min-h-11"
                                        >
                                            Tentar de novo
                                        </Button>
                                    )}
                                </div>
                            ) : (
                                mensagem.conteudo !== '' && (
                                    <p className="whitespace-pre-wrap">
                                        {mensagem.conteudo}
                                    </p>
                                )
                            )}

                            {mensagem.cartoes.map((cartao, indice) => (
                                <Cartao
                                    key={`${mensagem.id}-${indice}`}
                                    cartao={cartao}
                                    aoAtualizarAcao={atualizarAcao}
                                />
                            ))}

                            {(mensagem.status !== null ||
                                (mensagem.pendente &&
                                    mensagem.conteudo === '')) && (
                                <LinhaDeStatus
                                    texto={mensagem.status ?? 'Pensando…'}
                                    pendente={mensagem.pendente}
                                />
                            )}
                        </div>
                    ),
                )}
            </div>

            <CampoDeMensagem
                valor={texto}
                aoMudar={setTexto}
                enviando={enviando}
                sugestoes={SUGESTOES}
                aoEnviar={enviarDoCampo}
                aoParar={() => controleRef.current?.abort()}
                aoSugerir={(sugestao) => void enviar(sugestao)}
            />
        </div>
    );
}
