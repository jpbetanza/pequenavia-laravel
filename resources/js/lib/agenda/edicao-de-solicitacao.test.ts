import { describe, expect, it } from 'vite-plus/test';
import type { CartaoDeSolicitacao } from '@/types/agendamentos';
import {
    aplicarSugestaoDeEspaco,
    aplicarSugestaoDeHorario,
    ajustarQuantidade,
    alternarEquipamento,
    edicaoDoCartao,
    estaEmManutencao,
    payloadDaEdicao,
    situacaoDaAprovacao,
} from './edicao-de-solicitacao';

const cartao: CartaoDeSolicitacao = {
    id: 'sol-1',
    nome: 'Marcos',
    telefone: '11900001234',
    titulo: 'Encontro de casais',
    grupo_nome: 'Pastoral Familiar',
    data: '2026-10-10',
    inicio: '10:00',
    fim: '12:00',
    pessoas: 30,
    observacoes: null,
    status: 'pendente',
    motivo_recusa: null,
    espaco_id: 'salao',
    recebida_em: null,
    equipamentos: [{ id: 'mic', nome: 'Microfone', quantidade: 2 }],
    reserva_id: null,
    estado: 'conflito',
    conflitos: [],
    sugestoes: { espacos: [], horario: { inicio: '13:00', fim: '15:00' } },
};

describe('edicaoDoCartao', () => {
    it('copia os campos editáveis, sem compartilhar a lista de equipamentos', () => {
        const edicao = edicaoDoCartao(cartao);

        expect(edicao).toEqual({
            espaco_id: 'salao',
            data: '2026-10-10',
            inicio: '10:00',
            fim: '12:00',
            pessoas: 30,
            equipamentos: [{ id: 'mic', nome: 'Microfone', quantidade: 2 }],
        });

        edicao.equipamentos[0].quantidade = 5;
        expect(cartao.equipamentos[0].quantidade).toBe(2);
    });
});

describe('sugestões', () => {
    it('aplica o espaço sugerido sem mexer no horário', () => {
        const edicao = aplicarSugestaoDeEspaco(
            edicaoDoCartao(cartao),
            'auditorio',
        );

        expect(edicao.espaco_id).toBe('auditorio');
        expect(edicao.inicio).toBe('10:00');
    });

    it('aplica o horário sugerido e mantém data e espaço', () => {
        const edicao = aplicarSugestaoDeHorario(edicaoDoCartao(cartao), {
            inicio: '13:00',
            fim: '15:00',
        });

        expect(edicao).toMatchObject({
            espaco_id: 'salao',
            data: '2026-10-10',
            inicio: '13:00',
            fim: '15:00',
        });
    });
});

describe('equipamentos', () => {
    it('marca com uma unidade e desmarca na segunda vez', () => {
        const marcado = alternarEquipamento([], 'proj', 'Projetor');

        expect(marcado).toEqual([
            { id: 'proj', nome: 'Projetor', quantidade: 1 },
        ]);
        expect(alternarEquipamento(marcado, 'proj', 'Projetor')).toEqual([]);
    });

    it('limita a quantidade entre 1 e o total do equipamento', () => {
        const lista = [{ id: 'mic', nome: 'Microfone', quantidade: 1 }];

        expect(ajustarQuantidade(lista, 'mic', 9, 2)[0].quantidade).toBe(2);
        expect(ajustarQuantidade(lista, 'mic', 0, 2)[0].quantidade).toBe(1);
        expect(ajustarQuantidade(lista, 'mic', 2.7, 5)[0].quantidade).toBe(2);
    });

    it('manutenção vale até a data inclusive', () => {
        expect(estaEmManutencao('2026-10-10', '2026-10-10')).toBe(true);
        expect(estaEmManutencao('2026-10-09', '2026-10-10')).toBe(false);
        expect(estaEmManutencao(null, '2026-10-10')).toBe(false);
    });
});

describe('situacaoDaAprovacao', () => {
    it('só aprova sem conflito', () => {
        expect(situacaoDaAprovacao('sem_conflito')).toEqual({
            pode: true,
            motivo: null,
        });
    });

    it('explica por que não pode aprovar', () => {
        expect(situacaoDaAprovacao('escolha_um_espaco').motivo).toContain(
            'espaço',
        );
        expect(situacaoDaAprovacao('conflito').motivo).toContain('conflito');
        expect(situacaoDaAprovacao(null)).toEqual({
            pode: false,
            motivo: null,
        });
    });
});

describe('payloadDaEdicao', () => {
    it('manda só id e quantidade de cada equipamento', () => {
        expect(payloadDaEdicao(edicaoDoCartao(cartao))).toEqual({
            espaco_id: 'salao',
            data: '2026-10-10',
            inicio: '10:00',
            fim: '12:00',
            pessoas: 30,
            equipamentos: [{ id: 'mic', quantidade: 2 }],
        });
    });
});
