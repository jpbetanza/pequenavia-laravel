import { describe, expect, it } from 'vite-plus/test';
import { intervalosLivres, ocupacaoDoEspaco } from './ocupacao';

const m = (horario: string) => {
    const [h, min] = horario.split(':').map(Number);

    return h * 60 + min;
};

describe('intervalosLivres', () => {
    it('devolve a janela inteira quando não há reservas', () => {
        expect(
            intervalosLivres({ inicio: m('08:00'), fim: m('18:00') }, []),
        ).toEqual([{ inicio: m('08:00'), fim: m('18:00') }]);
    });

    it('desconta reservas e descarta sobras menores que 30 min', () => {
        const livres = intervalosLivres(
            { inicio: m('08:00'), fim: m('18:00') },
            [
                { inicio: m('09:00'), fim: m('12:00') },
                { inicio: m('12:10'), fim: m('17:50') },
            ],
        );

        // Sobras de 10 min (12:00–12:10 e 17:50–18:00) são descartadas.
        expect(livres).toEqual([{ inicio: m('08:00'), fim: m('09:00') }]);
    });

    it('ignora reservas fora da janela e sobreposições', () => {
        expect(
            intervalosLivres({ inicio: m('08:00'), fim: m('10:00') }, [
                { inicio: m('07:00'), fim: m('08:30') },
                { inicio: m('09:00'), fim: m('11:00') },
            ]),
        ).toEqual([{ inicio: m('08:30'), fim: m('09:00') }]);
    });
});

describe('ocupacaoDoEspaco', () => {
    const salao = {
        horarios: [{ dia_semana: 6, abre: '08:00', fecha: '18:00' }],
        bloqueios: [],
    };

    it('hachura o que fica antes e depois do horário de funcionamento', () => {
        const ocupacao = ocupacaoDoEspaco(salao, '2026-10-10', []);

        expect(ocupacao.fechadas).toEqual([
            {
                inicio: m('07:00'),
                fim: m('08:00'),
                tipo: 'fechado',
                texto: 'Fechado',
            },
            {
                inicio: m('18:00'),
                fim: m('22:00'),
                tipo: 'fechado',
                texto: 'Fechado',
            },
        ]);
        expect(ocupacao.livres).toEqual([
            { inicio: m('08:00'), fim: m('18:00') },
        ]);
    });

    it('fecha o dia inteiro quando o espaço não abre nesse dia da semana', () => {
        // 2026-10-11 é domingo (dia_semana 0), sem linha de horário.
        const ocupacao = ocupacaoDoEspaco(salao, '2026-10-11', []);

        expect(ocupacao.fechadas).toEqual([
            {
                inicio: m('07:00'),
                fim: m('22:00'),
                tipo: 'fechado',
                texto: 'Fechado neste dia da semana',
            },
        ]);
        expect(ocupacao.livres).toEqual([]);
    });

    it('mostra o motivo quando o dia está bloqueado', () => {
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
        const ocupacao = ocupacaoDoEspaco(bloqueado, '2026-10-10', []);

        expect(ocupacao.fechadas[0].texto).toBe('Bloqueado: Reforma');
        expect(ocupacao.livres).toEqual([]);
    });

    it('recorta os livres pelas reservas', () => {
        const ocupacao = ocupacaoDoEspaco(salao, '2026-10-10', [
            { inicio: m('09:00'), fim: m('12:00') },
        ]);

        expect(ocupacao.livres).toEqual([
            { inicio: m('08:00'), fim: m('09:00') },
            { inicio: m('12:00'), fim: m('18:00') },
        ]);
    });
});
