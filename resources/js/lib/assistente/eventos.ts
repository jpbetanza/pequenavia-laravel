import type {
    AcaoProposta,
    Cartao,
    EventoDoAssistente,
    MensagemDoPainel,
} from '@/types/assistente';
import type { EventoSse } from './sse';

type Dados = Record<string, unknown>;

function ehObjeto(valor: unknown): valor is Dados {
    return typeof valor === 'object' && valor !== null;
}

/** Converte um evento SSE do servidor no evento do painel. Devolve null se o formato não bater. */
export function eventoDoAssistente(
    evento: EventoSse,
): EventoDoAssistente | null {
    let dados: unknown;

    try {
        dados = JSON.parse(evento.dados);
    } catch {
        return null;
    }

    if (!ehObjeto(dados)) {
        return null;
    }

    switch (evento.evento) {
        case 'status':
            return typeof dados.texto === 'string'
                ? { tipo: 'status', texto: dados.texto }
                : null;

        case 'texto':
            return typeof dados.pedaco === 'string'
                ? { tipo: 'texto', pedaco: dados.pedaco }
                : null;

        case 'cartao':
            return typeof dados.tipo === 'string' && ehObjeto(dados.dados)
                ? {
                      tipo: 'cartao',
                      cartao: {
                          tipo: dados.tipo,
                          dados: dados.dados,
                      } as Cartao,
                  }
                : null;

        case 'fim': {
            const conversa = dados.conversa;

            if (typeof dados.mensagem_id !== 'string' || !ehObjeto(conversa)) {
                return null;
            }

            if (
                typeof conversa.id !== 'string' ||
                typeof conversa.titulo !== 'string'
            ) {
                return null;
            }

            return {
                tipo: 'fim',
                mensagemId: dados.mensagem_id,
                conversa: { id: conversa.id, titulo: conversa.titulo },
            };
        }

        case 'erro':
            return typeof dados.mensagem === 'string'
                ? { tipo: 'erro', mensagem: dados.mensagem }
                : null;

        default:
            return null;
    }
}

/** Acrescenta a pergunta do usuário e a resposta do assistente, ainda vazia e pendente. */
export function iniciarTroca(
    mensagens: MensagemDoPainel[],
    texto: string,
    ids: { usuario: string; assistente: string },
): MensagemDoPainel[] {
    return [
        ...mensagens,
        {
            id: ids.usuario,
            papel: 'usuario',
            conteudo: texto,
            cartoes: [],
            erro: false,
            status: null,
            pendente: false,
        },
        {
            id: ids.assistente,
            papel: 'assistente',
            conteudo: '',
            cartoes: [],
            erro: false,
            status: null,
            pendente: true,
        },
    ];
}

/** Aplica um evento do stream na última resposta do assistente. Devolve um array novo. */
export function aplicarEvento(
    mensagens: MensagemDoPainel[],
    evento: EventoDoAssistente,
): MensagemDoPainel[] {
    const indice = mensagens.findLastIndex(
        (mensagem) => mensagem.papel === 'assistente',
    );

    if (indice === -1) {
        return [...mensagens];
    }

    const atual = mensagens[indice];
    let alterada: MensagemDoPainel;

    switch (evento.tipo) {
        case 'status':
            alterada = { ...atual, status: evento.texto };
            break;

        case 'texto':
            alterada = {
                ...atual,
                conteudo: atual.conteudo + evento.pedaco,
                status: null,
            };
            break;

        case 'cartao':
            alterada = { ...atual, cartoes: [...atual.cartoes, evento.cartao] };
            break;

        case 'fim':
            alterada = {
                ...atual,
                id: evento.mensagemId,
                status: null,
                pendente: false,
            };
            break;

        case 'erro':
            alterada = {
                ...atual,
                conteudo: evento.mensagem,
                erro: true,
                status: null,
                pendente: false,
            };
            break;
    }

    return mensagens.map((mensagem, posicao) =>
        posicao === indice ? alterada : mensagem,
    );
}

/** Troca os dados do cartão de ação com o mesmo id (em qualquer mensagem), sem tocar nos outros cartões. */
export function substituirCartaoDeAcao(
    mensagens: MensagemDoPainel[],
    acao: AcaoProposta,
): MensagemDoPainel[] {
    return mensagens.map((mensagem) => ({
        ...mensagem,
        cartoes: mensagem.cartoes.map((cartao) =>
            cartao.tipo === 'acao_proposta' && cartao.dados.id === acao.id
                ? { tipo: 'acao_proposta', dados: acao }
                : cartao,
        ),
    }));
}

/** Mensagem única para falhas do assistente, igual à do servidor. */
export const MENSAGEM_DE_FALHA = 'Não consegui responder agora. Tente de novo.';
