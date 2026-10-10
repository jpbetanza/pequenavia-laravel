import { describe, expect, it } from 'vite-plus/test';
import type {
    EspacoDoCalendario,
    ReservaDoCalendario,
} from '@/types/agendamentos';
import {
    espacosLivresAgora,
    posicaoDoAgoraNaLista,
    reservasDoDia,
} from './agenda-do-celular';

const reserva = (
    id: string,
    data: string,
    inicio: string,
    fim: string,
    espacoId = 's1',
): ReservaDoCalendario => ({
    id,
    titulo: id,
    data,
    inicio,
    fim,
    pessoas: null,
    observacoes: null,
    serie_id: null,
    espaco_id: espacoId,
    grupo_id: null,
    grupo_nome: null,
    equipamentos: [],
});

const salao: EspacoDoCalendario = {
    id: 's1',
    nome: 'Salão',
    ativo: true,
    capacidade: 120,
    cor: 'bordo',
    recursos_fixos: [],
    horarios: [{ dia_semana: 6, abre: '08:00', fecha: '18:00' }],
    bloqueios: [],
};
const auditorio: EspacoDoCalendario = { ...salao, id: 's2', nome: 'Auditório' };

describe('reservasDoDia', () => {
    it('filtra o dia e ordena por início', () => {
        const lista = reservasDoDia(
            [
                reserva('b', '2026-10-10', '15:00', '17:00'),
                reserva('a', '2026-10-10', '09:00', '12:00'),
                reserva('x', '2026-10-11', '08:00', '09:00'),
            ],
            '2026-10-10',
        );

        expect(lista.map((item) => item.id)).toEqual(['a', 'b']);
    });
});

describe('posicaoDoAgoraNaLista', () => {
    const lista = [
        reserva('a', '2026-10-10', '09:00', '12:00'),
        reserva('b', '2026-10-10', '15:00', '17:00'),
    ];

    it('coloca o marcador depois das reservas que já começaram', () => {
        expect(posicaoDoAgoraNaLista(lista, true, '12:40')).toBe(1);
        expect(posicaoDoAgoraNaLista(lista, true, '08:00')).toBe(0);
    });

    it('não existe marcador fora de hoje', () => {
        expect(posicaoDoAgoraNaLista(lista, false, '12:40')).toBeNull();
    });
});

describe('espacosLivresAgora', () => {
    it('não oferece espaço inativo, mesmo aberto e livre', () => {
        const inativo = { ...auditorio, ativo: false };

        expect(
            espacosLivresAgora([inativo], [], '2026-10-10', '10:00'),
        ).toEqual([]);
    });

    it('exclui o espaço ocupado agora e o que está fechado', () => {
        const reservas = [reserva('a', '2026-10-10', '09:00', '12:00', 's1')];

        expect(
            espacosLivresAgora(
                [salao, auditorio],
                reservas,
                '2026-10-10',
                '10:00',
            ).map((espaco) => espaco.id),
        ).toEqual(['s2']);
        expect(
            espacosLivresAgora([salao, auditorio], [], '2026-10-10', '19:00'),
        ).toEqual([]);
    });

    it('exclui o espaço bloqueado na data', () => {
        const bloqueado = {
            ...auditorio,
            bloqueios: [
                {
                    id: 'b',
                    inicio: '2026-10-10',
                    fim: '2026-10-10',
                    motivo: 'Reforma',
                },
            ],
        };

        expect(
            espacosLivresAgora([bloqueado], [], '2026-10-10', '10:00'),
        ).toEqual([]);
    });
});
