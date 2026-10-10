import { describe, expect, it } from 'vite-plus/test';
import type { EspacoDoCalendario } from '@/types/agendamentos';
import { estadoDaSolicitacao } from './solicitacoes';

// 2026-10-10 é sábado (dia_semana 6).
const salao: EspacoDoCalendario = {
    id: 'salao',
    nome: 'Salão',
    capacidade: 120,
    cor: 'bordo',
    recursos_fixos: [],
    horarios: [{ dia_semana: 6, abre: '08:00', fecha: '18:00' }],
    bloqueios: [],
};

const pedido = {
    espaco_id: 'salao',
    data: '2026-10-10',
    inicio: '10:00',
    fim: '12:00',
};

describe('estadoDaSolicitacao', () => {
    it('pede a escolha de um espaço quando não há espaço', () => {
        expect(
            estadoDaSolicitacao({ ...pedido, espaco_id: null }, [salao], []),
        ).toEqual({ situacao: 'sem_espaco', motivos: [] });
    });

    it('sem conflito quando o espaço está aberto e livre', () => {
        expect(estadoDaSolicitacao(pedido, [salao], []).situacao).toBe(
            'sem_conflito',
        );
    });

    it('conflito quando há reserva sobreposta no mesmo espaço e dia', () => {
        const estado = estadoDaSolicitacao(
            pedido,
            [salao],
            [
                {
                    espaco_id: 'salao',
                    data: '2026-10-10',
                    inicio: '11:00',
                    fim: '13:00',
                    titulo: 'Encontro de noivos',
                },
            ],
        );

        expect(estado.situacao).toBe('conflito');
        expect(estado.motivos[0]).toContain('Encontro de noivos');
    });

    it('encostar em uma reserva não é conflito', () => {
        const estado = estadoDaSolicitacao(
            pedido,
            [salao],
            [
                {
                    espaco_id: 'salao',
                    data: '2026-10-10',
                    inicio: '12:00',
                    fim: '14:00',
                    titulo: 'Outra',
                },
            ],
        );

        expect(estado.situacao).toBe('sem_conflito');
    });

    it('conflito quando o espaço está fechado no dia ou fora do horário', () => {
        const semHorario = { ...salao, horarios: [] };

        expect(estadoDaSolicitacao(pedido, [semHorario], []).motivos).toEqual([
            'O espaço não abre neste dia da semana.',
        ]);

        const tarde = estadoDaSolicitacao(
            { ...pedido, fim: '19:00' },
            [salao],
            [],
        );

        expect(tarde.situacao).toBe('conflito');
        expect(tarde.motivos[0]).toContain('fecha às 18:00');
    });

    it('conflito quando o espaço está bloqueado na data', () => {
        const bloqueado = {
            ...salao,
            bloqueios: [
                {
                    id: 'b',
                    inicio: '2026-10-09',
                    fim: '2026-10-11',
                    motivo: 'Reforma',
                },
            ],
        };

        expect(estadoDaSolicitacao(pedido, [bloqueado], []).motivos).toEqual([
            'Bloqueado: Reforma.',
        ]);
    });

    it('conflito quando o espaço escolhido não está entre os ativos', () => {
        expect(
            estadoDaSolicitacao({ ...pedido, espaco_id: 'sumido' }, [salao], [])
                .situacao,
        ).toBe('conflito');
    });
});
