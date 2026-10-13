import { List, SquarePen, Upload, X } from 'lucide-react';
import { useEffect, useRef, useState, type DragEvent } from 'react';
import { toast } from 'sonner';
import {
    show as mostrarConversa,
    store as criarConversa,
} from '@/actions/App/Http/Controllers/Assistente/ConversaController';
import { store as enviarAoAssistente } from '@/actions/App/Http/Controllers/Assistente/MensagemController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    AnexoRecusado,
    type AnexoPreparado,
    DICA_DOS_ANEXOS,
    juntarAnexos,
    MAX_ANEXOS,
    prepararAnexo,
    resumoDoAnexo,
} from '@/lib/assistente/anexos';
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
    AnexoDaMensagem,
    Cartao as TipoDeCartao,
    MensagemDoPainel,
} from '@/types/assistente';
import { Anexo } from './anexo';
import { CampoDeMensagem } from './campo-de-mensagem';
import { Cartao } from './cartoes/cartao';
import { ConfirmarTodas } from './cartoes/confirmar-todas';
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
        anexos?: AnexoDaMensagem[];
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

/** Propostas ainda pendentes nos cartões de uma resposta. */
function propostasPendentes(mensagem: MensagemDoPainel): AcaoProposta[] {
    return mensagem.cartoes.flatMap((cartao) =>
        cartao.tipo === 'acao_proposta' && cartao.dados.status === 'proposta'
            ? [cartao.dados]
            : [],
    );
}

/** O arrasto traz arquivos (e não texto ou um link da página)? */
function trazArquivos(evento: DragEvent): boolean {
    return evento.dataTransfer.types.includes('Files');
}

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
        conteudo: mensagem.conteudo ?? '',
        anexos: mensagem.anexos ?? [],
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
    const [anexos, setAnexos] = useState<AnexoPreparado[]>([]);
    const [preparando, setPreparando] = useState(0);
    const [arrastando, setArrastando] = useState(false);
    const registroRef = useRef<HTMLDivElement>(null);
    const emAndamento = useRef(false);
    const controleRef = useRef<AbortController | null>(null);
    // Entrar num filho dispara dragleave no pai: conta as entradas para saber quando o arrasto saiu do painel.
    const profundidadeDoArrasto = useRef(0);
    // Anexos de cada pergunta enviada nesta sessão do painel, para "Tentar de novo" reenviá-los.
    const anexosEnviados = useRef(new Map<string, AnexoPreparado[]>());

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

    async function adicionarArquivos(arquivos: File[]) {
        setPreparando((atual) => atual + 1);

        try {
            const lidos = await Promise.allSettled(arquivos.map(prepararAnexo));
            const prontos = lidos.flatMap((lido) =>
                lido.status === 'fulfilled' ? [lido.value] : [],
            );

            for (const lido of lidos) {
                if (lido.status === 'rejected') {
                    toast.error(
                        lido.reason instanceof AnexoRecusado
                            ? lido.reason.message
                            : 'Não consegui ler um dos arquivos.',
                    );
                }
            }

            setAnexos((atuais) => {
                const { anexos: juntos, recusadosPeloLimite } = juntarAnexos(
                    atuais,
                    prontos,
                );

                if (recusadosPeloLimite > 0) {
                    toast.error(
                        `Envie até ${MAX_ANEXOS} arquivos por mensagem.`,
                    );
                }

                return juntos;
            });
        } finally {
            setPreparando((atual) => atual - 1);
        }
    }

    function aoEntrarArrastando(evento: DragEvent<HTMLDivElement>) {
        if (!trazArquivos(evento)) {
            return;
        }

        evento.preventDefault();
        profundidadeDoArrasto.current++;
        setArrastando(true);
    }

    function aoArrastarPorCima(evento: DragEvent<HTMLDivElement>) {
        if (!trazArquivos(evento)) {
            return;
        }

        // Sem isso o navegador não deixa soltar e abre o arquivo na aba.
        evento.preventDefault();
        evento.dataTransfer.dropEffect = 'copy';
    }

    function aoSairArrastando(evento: DragEvent<HTMLDivElement>) {
        if (!trazArquivos(evento)) {
            return;
        }

        profundidadeDoArrasto.current = Math.max(
            0,
            profundidadeDoArrasto.current - 1,
        );

        if (profundidadeDoArrasto.current === 0) {
            setArrastando(false);
        }
    }

    function aoSoltar(evento: DragEvent<HTMLDivElement>) {
        if (!trazArquivos(evento)) {
            return;
        }

        evento.preventDefault();
        profundidadeDoArrasto.current = 0;
        setArrastando(false);

        const arquivos = Array.from(evento.dataTransfer.files);

        if (arquivos.length > 0) {
            void adicionarArquivos(arquivos);
        }
    }

    async function enviar(
        pergunta: string,
        anexosDoEnvio: AnexoPreparado[] = [],
    ) {
        const limpa = pergunta.trim();

        if (
            (limpa === '' && anexosDoEnvio.length === 0) ||
            emAndamento.current
        ) {
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
        anexosEnviados.current.set(ids.usuario, anexosDoEnvio);
        setMensagens((atuais) =>
            iniciarTroca(atuais, limpa, ids, anexosDoEnvio.map(resumoDoAnexo)),
        );

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
                anexosDoEnvio,
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
                // Nada chegou: a troca some e a pergunta (com os anexos) volta para o campo.
                setMensagens((atuais) =>
                    atuais.filter(
                        (mensagem) =>
                            mensagem.id !== ids.usuario &&
                            mensagem.id !== ids.assistente,
                    ),
                );
                setTexto((atual) => (atual === '' ? limpa : atual));
                setAnexos((atuais) =>
                    atuais.length === 0 ? anexosDoEnvio : atuais,
                );

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
        const anexosDoCampo = anexos;

        setTexto('');
        setAnexos([]);
        void enviar(pergunta, anexosDoCampo);
    }

    function tentarDeNovo() {
        const indice = mensagens.findLastIndex(
            (mensagem) => mensagem.papel === 'usuario',
        );

        if (indice === -1) {
            return;
        }

        const { id, conteudo } = mensagens[indice];
        // Anexos de uma conversa reaberta não estão mais no navegador: a pergunta volta sem eles, e o
        // assistente ainda vê os arquivos na mensagem anterior, que continua no histórico.
        const anexosDeNovo = anexosEnviados.current.get(id) ?? [];
        const pergunta =
            conteudo !== '' || anexosDeNovo.length > 0
                ? conteudo
                : 'Tente de novo com os arquivos que enviei.';

        setMensagens((atuais) => atuais.slice(0, indice));
        void enviar(pergunta, anexosDeNovo);
    }

    function atualizarAcao(acao: AcaoProposta) {
        setMensagens((atuais) => substituirCartaoDeAcao(atuais, acao));
    }

    const vazia = mensagens.length === 0;
    const ultimaId = mensagens.at(-1)?.id;

    return (
        <div
            className="relative flex min-h-0 flex-1 flex-col"
            onDragEnter={aoEntrarArrastando}
            onDragOver={aoArrastarPorCima}
            onDragLeave={aoSairArrastando}
            onDrop={aoSoltar}
        >
            {arrastando && (
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-2 z-20 flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-primary bg-background/95 p-6 text-center"
                >
                    <span className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <Upload aria-hidden="true" className="size-6" />
                    </span>
                    <span className="font-serif text-2xl font-semibold">
                        Solte para anexar
                    </span>
                    <span className="max-w-64 text-sm text-muted-foreground">
                        {DICA_DOS_ANEXOS}
                    </span>
                </div>
            )}

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
                    <div className="my-auto flex flex-col gap-1.5 text-center text-muted-foreground">
                        <p>Pergunte sobre a agenda ou os certificados.</p>
                        <p className="text-sm">
                            Para cadastrar espaços, equipamentos ou grupos de
                            uma vez, anexe ou arraste uma planilha.
                        </p>
                    </div>
                )}

                {mensagens.map((mensagem) =>
                    mensagem.papel === 'usuario' ? (
                        <div key={mensagem.id} className="flex justify-end">
                            <div className="flex max-w-[85%] min-w-0 flex-col gap-2 rounded-2xl rounded-br-sm bg-primary px-3.5 py-2.5 text-primary-foreground">
                                {mensagem.anexos.length > 0 && (
                                    <ul
                                        aria-label="Arquivos anexados"
                                        className="-mx-1.5 flex flex-col gap-1.5"
                                    >
                                        {mensagem.anexos.map((anexo) => (
                                            <li
                                                key={anexo.id}
                                                className="flex min-w-0"
                                            >
                                                <Anexo
                                                    anexo={anexo}
                                                    variante="mensagem"
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {mensagem.conteudo !== '' && (
                                    <p className="whitespace-pre-wrap">
                                        {mensagem.conteudo}
                                    </p>
                                )}
                            </div>
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

                            {!mensagem.pendente &&
                                propostasPendentes(mensagem).length > 1 && (
                                    <ConfirmarTodas
                                        acoes={propostasPendentes(mensagem)}
                                        aoAtualizarAcao={atualizarAcao}
                                    />
                                )}

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
                anexos={anexos}
                preparandoAnexos={preparando > 0}
                aoEscolherArquivos={(arquivos) =>
                    void adicionarArquivos(arquivos)
                }
                aoRemoverAnexo={(id) =>
                    setAnexos((atuais) =>
                        atuais.filter((anexo) => anexo.id !== id),
                    )
                }
            />
        </div>
    );
}
