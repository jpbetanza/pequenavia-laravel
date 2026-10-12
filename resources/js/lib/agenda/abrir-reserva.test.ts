import { describe, expect, it } from 'vite-plus/test';
import {
    lerPedidoDeNovaReserva,
    removerParametrosDeNovaReserva,
} from './abrir-reserva';

describe('lerPedidoDeNovaReserva', () => {
    it('devolve null quando a URL não pede uma nova reserva', () => {
        expect(lerPedidoDeNovaReserva('?data=2026-10-18&visao=dia')).toBeNull();
        expect(lerPedidoDeNovaReserva('?nova_reserva=0')).toBeNull();
        expect(lerPedidoDeNovaReserva('')).toBeNull();
    });

    it('lê espaço e horário quando a URL pede uma nova reserva', () => {
        expect(
            lerPedidoDeNovaReserva(
                '?data=2026-10-18&visao=dia&nova_reserva=1&espaco=abc-123&inicio=14:00',
            ),
        ).toEqual({ espacoId: 'abc-123', inicio: '14:00' });
    });

    it('aceita o pedido sem espaço e sem horário', () => {
        expect(lerPedidoDeNovaReserva('?nova_reserva=1')).toEqual({
            espacoId: null,
            inicio: null,
        });
    });

    it('descarta horário inválido e espaço vazio', () => {
        expect(
            lerPedidoDeNovaReserva('?nova_reserva=1&espaco=&inicio=25:00'),
        ).toEqual({ espacoId: null, inicio: null });
        expect(lerPedidoDeNovaReserva('?nova_reserva=1&inicio=14h')).toEqual({
            espacoId: null,
            inicio: null,
        });
    });
});

describe('removerParametrosDeNovaReserva', () => {
    it('tira os três parâmetros e mantém os demais', () => {
        expect(
            removerParametrosDeNovaReserva(
                'https://app.test/agendamentos/calendario?data=2026-10-18&visao=dia&nova_reserva=1&espaco=abc&inicio=14:00',
            ),
        ).toBe(
            'https://app.test/agendamentos/calendario?data=2026-10-18&visao=dia',
        );
    });

    it('não deixa ? sobrando quando não há mais parâmetros', () => {
        expect(
            removerParametrosDeNovaReserva(
                'https://app.test/agendamentos/calendario?nova_reserva=1',
            ),
        ).toBe('https://app.test/agendamentos/calendario');
    });
});
