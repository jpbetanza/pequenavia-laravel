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

/**
 * Chave da proposta: a que o servidor manda em `chave`. Sem ela (cartão antigo), reserva vale 'reserva'
 * e cadastro vale o próprio id, para que um cartão antigo de cadastro nunca expire outro.
 */
function chaveDaAcao(acao: AcaoProposta): string {
    if (acao.chave !== undefined) {
        return acao.chave;
    }

    return acao.tipo === 'criar_reserva' ? 'reserva' : acao.id;
}

/**
 * Com uma proposta nova, as propostas pendentes anteriores com a mesma chave já estão expiradas no servidor:
 * marca-as aqui também, para o cartão não oferecer Confirmar. Propostas de outra chave e a própria nova ficam como estão.
 */
function expirarPropostasAnteriores(
    mensagens: MensagemDoPainel[],
    nova: AcaoProposta,
): MensagemDoPainel[] {
    const chave = chaveDaAcao(nova);

    return mensagens.map((mensagem) => ({
        ...mensagem,
        cartoes: mensagem.cartoes.map((cartao) =>
            cartao.tipo === 'acao_proposta' &&
            cartao.dados.status === 'proposta' &&
            cartao.dados.id !== nova.id &&
            chaveDaAcao(cartao.dados) === chave
                ? {
                      tipo: 'acao_proposta',
                      dados: { ...cartao.dados, status: 'expirada' },
                  }
                : cartao,
        ),
    }));
}

/** Aplica um evento do stream na última resposta do assistente. Devolve um array novo. */
export function aplicarEvento(
    mensagensAntigas: MensagemDoPainel[],
    evento: EventoDoAssistente,
): MensagemDoPainel[] {
    const mensagens =
        evento.tipo === 'cartao' && evento.cartao.tipo === 'acao_proposta'
            ? expirarPropostasAnteriores(mensagensAntigas, evento.cartao.dados)
            : mensagensAntigas;
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

/** Cartão de ação atual que o 422 de confirmar ou descartar traz em `acao`. Devolve null se não houver um válido. */
export function acaoDoCorpoDeErro(
    corpo: Record<string, unknown> | null,
): AcaoProposta | null {
    const acao = corpo?.acao;

    return ehObjeto(acao) &&
        typeof acao.id === 'string' &&
        typeof acao.status === 'string'
        ? (acao as unknown as AcaoProposta)
        : null;
}

/** Mensagem de um erro do servidor (ex.: "O cadastro mudou desde a proposta."). Devolve null se não houver texto. */
export function mensagemDoCorpoDeErro(
    corpo: Record<string, unknown> | null,
): string | null {
    return typeof corpo?.message === 'string' ? corpo.message : null;
}
