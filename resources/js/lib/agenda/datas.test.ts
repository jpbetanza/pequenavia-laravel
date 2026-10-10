import { describe, expect, it } from 'vite-plus/test';
import {
    compararDatas,
    dataPorExtenso,
    deslocarMes,
    diaDaSemana,
    diasDaSemana,
    diferencaEmDias,
    ehDataISO,
    fimDaSemana,
    formatarDiaCurto,
    gradeDoMes,
    hojeDe,
    horaDe,
    inicioDaSemana,
    intervaloPorExtenso,
    rotuloDoMes,
    somarDias,
} from './datas';

describe('somarDias', () => {
    it('atravessa virada de mês, de ano e fevereiro bissexto', () => {
        expect(somarDias('2026-10-31', 1)).toBe('2026-11-01');
        expect(somarDias('2026-12-31', 1)).toBe('2027-01-01');
        expect(somarDias('2028-02-28', 1)).toBe('2028-02-29');
        expect(somarDias('2027-02-28', 1)).toBe('2027-03-01');
    });

    it('aceita número negativo', () => {
        expect(somarDias('2027-01-01', -1)).toBe('2026-12-31');
    });
});

describe('diferencaEmDias e compararDatas', () => {
    it('conta dias corridos e ordena', () => {
        expect(diferencaEmDias('2026-10-05', '2026-10-11')).toBe(6);
        expect(diferencaEmDias('2026-10-11', '2026-10-05')).toBe(-6);
        expect(compararDatas('2026-10-04', '2026-10-05')).toBeLessThan(0);
        expect(compararDatas('2026-10-05', '2026-10-05')).toBe(0);
    });
});

describe('início e dias da semana', () => {
    it('começa a semana na segunda-feira, inclusive em domingo', () => {
        expect(diaDaSemana('2026-10-11')).toBe(0);
        expect(inicioDaSemana('2026-10-11')).toBe('2026-10-05');
        expect(inicioDaSemana('2026-10-05')).toBe('2026-10-05');
        expect(inicioDaSemana('2026-10-10')).toBe('2026-10-05');
    });

    it('atravessa o ano sem erro de fuso', () => {
        expect(inicioDaSemana('2027-01-01')).toBe('2026-12-28');
    });

    it('devolve sete dias de segunda a domingo, e o domingo fecha a semana', () => {
        const semana = diasDaSemana('2026-10-10');

        expect(semana).toEqual([
            '2026-10-05',
            '2026-10-06',
            '2026-10-07',
            '2026-10-08',
            '2026-10-09',
            '2026-10-10',
            '2026-10-11',
        ]);
        expect(fimDaSemana('2026-10-10')).toBe('2026-10-11');
    });
});

describe('formatação pt-BR', () => {
    it('formata o dia curto como na agenda', () => {
        expect(formatarDiaCurto('2026-10-05')).toBe('seg., 5 de out.');
    });

    it('formata a data por extenso com dia da semana', () => {
        expect(dataPorExtenso('2026-10-10')).toBe(
            'sábado, 10 de outubro de 2026',
        );
    });

    it('formata o intervalo da semana no mesmo mês, entre meses e entre anos', () => {
        expect(intervaloPorExtenso('2026-10-05', '2026-10-11')).toBe(
            '5 a 11 de outubro de 2026',
        );
        expect(intervaloPorExtenso('2026-09-28', '2026-10-04')).toBe(
            '28 de setembro a 4 de outubro de 2026',
        );
        expect(intervaloPorExtenso('2026-12-28', '2027-01-03')).toBe(
            '28 de dezembro de 2026 a 3 de janeiro de 2027',
        );
    });

    it('rotula o mês com a inicial maiúscula', () => {
        expect(rotuloDoMes('2026-10-10')).toBe('Outubro 2026');
    });
});

describe('mês do mini calendário', () => {
    it('desloca para o primeiro dia do mês anterior e seguinte', () => {
        expect(deslocarMes('2026-10-10', 1)).toBe('2026-11-01');
        expect(deslocarMes('2026-01-15', -1)).toBe('2025-12-01');
    });

    it('monta semanas de segunda a domingo cobrindo o mês inteiro', () => {
        const semanas = gradeDoMes('2026-10-10');

        expect(semanas).toHaveLength(5);
        expect(semanas[0][0]).toBe('2026-09-28');
        expect(semanas[4][6]).toBe('2026-11-01');
        expect(semanas.flat()).toContain('2026-10-31');
    });
});

describe('hoje a partir de agora', () => {
    it('usa a data do próprio "agora", no fuso que a string traz', () => {
        expect(hojeDe('2026-10-04T23:00:00-03:00')).toBe('2026-10-04');
        expect(horaDe('2026-10-04T23:00:00-03:00')).toBe('23:00');
    });
});

describe('ehDataISO', () => {
    it('recusa datas que não existem no calendário', () => {
        expect(ehDataISO('2026-10-04')).toBe(true);
        expect(ehDataISO('2026-02-30')).toBe(false);
        expect(ehDataISO('04/10/2026')).toBe(false);
    });
});
