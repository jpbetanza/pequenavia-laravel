import { describe, expect, it } from 'vite-plus/test';
import {
    lerPedidoDeNovaReserva,
    removerParametrosDeNovaReserva,
} from './abrir-reserva';

const SEM_PREENCHIMENTO = {
    espacoId: null,
    inicio: null,
    fim: null,
    titulo: null,
    grupoId: null,
    equipamentos: [],
    pessoas: null,
    observacoes: null,
};

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
        ).toEqual({
            ...SEM_PREENCHIMENTO,
            espacoId: 'abc-123',
            inicio: '14:00',
        });
    });

    it('aceita o pedido sem espaço e sem horário', () => {
        expect(lerPedidoDeNovaReserva('?nova_reserva=1')).toEqual(
            SEM_PREENCHIMENTO,
        );
    });

    it('descarta horário inválido e espaço vazio', () => {
        expect(
            lerPedidoDeNovaReserva('?nova_reserva=1&espaco=&inicio=25:00'),
        ).toEqual(SEM_PREENCHIMENTO);
        expect(lerPedidoDeNovaReserva('?nova_reserva=1&inicio=14h')).toEqual(
            SEM_PREENCHIMENTO,
        );
    });

    it('lê título, fim, grupo e equipamentos com quantidade', () => {
        expect(
            lerPedidoDeNovaReserva(
                '?nova_reserva=1&titulo=Catequese%20infantil&fim=17:30&grupo=g-1&equipamentos=e-1:2,e-2:1',
            ),
        ).toEqual({
            ...SEM_PREENCHIMENTO,
            titulo: 'Catequese infantil',
            fim: '17:30',
            grupoId: 'g-1',
            equipamentos: [
                { id: 'e-1', quantidade: 2 },
                { id: 'e-2', quantidade: 1 },
            ],
        });
    });

    it('tira espaços do título e ignora título vazio ou longo demais', () => {
        expect(
            lerPedidoDeNovaReserva('?nova_reserva=1&titulo=%20%20Reunião%20'),
        ).toMatchObject({ titulo: 'Reunião' });
        expect(
            lerPedidoDeNovaReserva('?nova_reserva=1&titulo=%20%20'),
        ).toMatchObject({ titulo: null });
        expect(
            lerPedidoDeNovaReserva(`?nova_reserva=1&titulo=${'x'.repeat(161)}`),
        ).toMatchObject({ titulo: null });
    });

    it('descarta fim inválido e grupo vazio', () => {
        expect(
            lerPedidoDeNovaReserva('?nova_reserva=1&fim=24:00&grupo='),
        ).toMatchObject({ fim: null, grupoId: null });
        expect(lerPedidoDeNovaReserva('?nova_reserva=1&fim=18h')).toMatchObject(
            { fim: null },
        );
    });

    it('ignora equipamentos com id vazio, quantidade inválida ou fora do limite', () => {
        expect(
            lerPedidoDeNovaReserva(
                '?nova_reserva=1&equipamentos=:2,e-2:0,e-3:x,e-4:1001,e-5,e-6:3',
            ),
        ).toMatchObject({
            equipamentos: [{ id: 'e-6', quantidade: 3 }],
        });
    });

    it('usa só a primeira ocorrência de um equipamento repetido', () => {
        expect(
            lerPedidoDeNovaReserva('?nova_reserva=1&equipamentos=e-1:2,e-1:5'),
        ).toMatchObject({ equipamentos: [{ id: 'e-1', quantidade: 2 }] });
    });
});

describe('lerPedidoDeNovaReserva com pessoas e observações', () => {
    it('lê pessoas como inteiro de 1 a 2000', () => {
        expect(
            lerPedidoDeNovaReserva('?nova_reserva=1&pessoas=35'),
        ).toMatchObject({ pessoas: 35 });
        expect(
            lerPedidoDeNovaReserva('?nova_reserva=1&pessoas=2000'),
        ).toMatchObject({ pessoas: 2000 });
    });

    it('descarta pessoas fora do intervalo ou que não são inteiros', () => {
        for (const valor of ['0', '2001', '-3', '2.5', '12a', '', '1e2']) {
            expect(
                lerPedidoDeNovaReserva(`?nova_reserva=1&pessoas=${valor}`),
            ).toMatchObject({ pessoas: null });
        }
    });

    it('lê observações com espaços aparados e descarta vazias ou acima de 1000 caracteres', () => {
        expect(
            lerPedidoDeNovaReserva(
                '?nova_reserva=1&observacoes=%20Trazer%20cadeiras%0Acom%20capa%20%20',
            ),
        ).toMatchObject({ observacoes: 'Trazer cadeiras\ncom capa' });
        expect(
            lerPedidoDeNovaReserva('?nova_reserva=1&observacoes=%20%20'),
        ).toMatchObject({ observacoes: null });
        expect(
            lerPedidoDeNovaReserva(
                `?nova_reserva=1&observacoes=${'x'.repeat(1001)}`,
            ),
        ).toMatchObject({ observacoes: null });
        expect(
            lerPedidoDeNovaReserva(
                `?nova_reserva=1&observacoes=${'x'.repeat(1000)}`,
            ),
        ).toMatchObject({ observacoes: 'x'.repeat(1000) });
    });
});

describe('removerParametrosDeNovaReserva', () => {
    it('tira os parâmetros de nova reserva e mantém os demais', () => {
        expect(
            removerParametrosDeNovaReserva(
                'https://app.test/agendamentos/calendario?data=2026-10-18&visao=dia&nova_reserva=1&espaco=abc&inicio=14:00&titulo=X&fim=15:00&grupo=g&equipamentos=e:1&pessoas=3&observacoes=x',
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
