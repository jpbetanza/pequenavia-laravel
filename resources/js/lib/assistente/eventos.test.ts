import { describe, expect, it } from 'vite-plus/test';
import type {
    AcaoProposta,
    Cartao,
    MensagemDoPainel,
} from '@/types/assistente';
import {
    aplicarEvento,
    eventoDoAssistente,
    iniciarTroca,
    substituirCartaoDeAcao,
} from './eventos';
import type { EventoSse } from './sse';

const sse = (evento: string, dados: unknown): EventoSse => ({
    evento,
    dados: typeof dados === 'string' ? dados : JSON.stringify(dados),
});

const cartaoReservas: Cartao = {
    tipo: 'reservas',
    dados: { inicio: '2026-10-15', fim: '2026-10-15', reservas: [] },
};

const acao = (
    id: string,
    titulo: string,
    status: AcaoProposta['status'] = 'proposta',
): AcaoProposta => ({
    id,
    status,
    resumo: {
        titulo,
        grupo_nome: null,
        espaco_id: 'e1',
        espaco_nome: 'Salão',
        data: '2026-10-17',
        inicio: '18:00',
        fim: '20:00',
        pessoas: null,
        equipamentos: [],
        observacoes: null,
    },
    resultado: null,
});

const cartaoAcao = (dados: AcaoProposta): Cartao => ({
    tipo: 'acao_proposta',
    dados,
});

const usuario = (id: string, conteudo: string): MensagemDoPainel => ({
    id,
    papel: 'usuario',
    conteudo,
    cartoes: [],
    erro: false,
    status: null,
    pendente: false,
});

const assistente = (
    id: string,
    extra: Partial<MensagemDoPainel> = {},
): MensagemDoPainel => ({
    id,
    papel: 'assistente',
    conteudo: '',
    cartoes: [],
    erro: false,
    status: null,
    pendente: false,
    ...extra,
});

describe('eventoDoAssistente', () => {
    it('converte status, texto, cartão, fim e erro', () => {
        expect(
            eventoDoAssistente(
                sse('status', { texto: 'Consultando a agenda…' }),
            ),
        ).toEqual({
            tipo: 'status',
            texto: 'Consultando a agenda…',
        });
        expect(eventoDoAssistente(sse('texto', { pedaco: 'Oi' }))).toEqual({
            tipo: 'texto',
            pedaco: 'Oi',
        });
        expect(
            eventoDoAssistente(
                sse('cartao', {
                    tipo: 'reservas',
                    dados: cartaoReservas.dados,
                }),
            ),
        ).toEqual({
            tipo: 'cartao',
            cartao: cartaoReservas,
        });
        expect(
            eventoDoAssistente(
                sse('fim', {
                    mensagem_id: 'm2',
                    conversa: { id: 'c1', titulo: 'Salão' },
                }),
            ),
        ).toEqual({
            tipo: 'fim',
            mensagemId: 'm2',
            conversa: { id: 'c1', titulo: 'Salão' },
        });
        expect(eventoDoAssistente(sse('erro', { mensagem: 'Falhou' }))).toEqual(
            { tipo: 'erro', mensagem: 'Falhou' },
        );
    });

    it('devolve null para JSON inválido ou evento desconhecido', () => {
        expect(eventoDoAssistente(sse('texto', '{nao-json'))).toBeNull();
        expect(eventoDoAssistente(sse('desconhecido', { x: 1 }))).toBeNull();
    });
});

describe('iniciarTroca', () => {
    it('acrescenta a mensagem do usuário e uma do assistente pendente e vazia', () => {
        const anteriores = [usuario('u0', 'Oi'), assistente('a0')];
        const resultado = iniciarTroca(anteriores, 'Tem sala sábado?', {
            usuario: 'u1',
            assistente: 'a1',
        });

        expect(resultado).toEqual([
            ...anteriores,
            usuario('u1', 'Tem sala sábado?'),
            assistente('a1', { conteudo: '', pendente: true }),
        ]);
        expect(anteriores).toHaveLength(2);
    });
});

describe('aplicarEvento', () => {
    it('acumula status, texto e cartão até o fim', () => {
        let mensagens = iniciarTroca([], 'Quais reservas?', {
            usuario: 'u1',
            assistente: 'a1',
        });

        mensagens = aplicarEvento(mensagens, {
            tipo: 'status',
            texto: 'Consultando…',
        });
        mensagens = aplicarEvento(mensagens, {
            tipo: 'texto',
            pedaco: 'Aqui ',
        });
        mensagens = aplicarEvento(mensagens, {
            tipo: 'texto',
            pedaco: 'estão.',
        });
        mensagens = aplicarEvento(mensagens, {
            tipo: 'cartao',
            cartao: cartaoReservas,
        });
        mensagens = aplicarEvento(mensagens, {
            tipo: 'fim',
            mensagemId: 'm-servidor',
            conversa: { id: 'c1', titulo: 'Reservas' },
        });

        const ultima = mensagens[mensagens.length - 1];
        expect(ultima).toEqual({
            id: 'm-servidor',
            papel: 'assistente',
            conteudo: 'Aqui estão.',
            cartoes: [cartaoReservas],
            erro: false,
            status: null,
            pendente: false,
        });
        expect(mensagens[0]).toEqual(usuario('u1', 'Quais reservas?'));
    });

    it('erro marca a mensagem com erro, usa a mensagem como conteúdo e encerra a espera', () => {
        let mensagens = iniciarTroca([], 'Oi', {
            usuario: 'u1',
            assistente: 'a1',
        });
        mensagens = aplicarEvento(mensagens, {
            tipo: 'erro',
            mensagem: 'Serviço indisponível.',
        });

        expect(mensagens[mensagens.length - 1]).toEqual(
            assistente('a1', {
                conteudo: 'Serviço indisponível.',
                erro: true,
                pendente: false,
            }),
        );
    });
});

describe('substituirCartaoDeAcao', () => {
    it('troca os dados do cartão de ação com o mesmo id e não mexe nos outros', () => {
        const proposta = acao('ac1', 'Reunião');
        const outra = acao('ac2', 'Outra');
        const mensagens: MensagemDoPainel[] = [
            assistente('a1', {
                cartoes: [cartaoAcao(proposta), cartaoReservas],
            }),
            assistente('a2', { cartoes: [cartaoAcao(outra)] }),
        ];

        const confirmada = acao('ac1', 'Reunião', 'confirmada');
        const resultado = substituirCartaoDeAcao(mensagens, confirmada);

        expect(resultado[0].cartoes).toEqual([
            cartaoAcao(confirmada),
            cartaoReservas,
        ]);
        expect(resultado[1].cartoes).toEqual([cartaoAcao(outra)]);
        expect(mensagens[0].cartoes[0]).toEqual(cartaoAcao(proposta));
    });
});
