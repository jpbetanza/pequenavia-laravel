import { describe, expect, it } from 'vite-plus/test';
import {
    dataDeHoje,
    diasEntre,
    diaRelativo,
    formatarCusto,
    formatarData,
    formatarInstante,
    formatarNumero,
    formatarPct,
    hojeNoFuso,
    rotuloDaFrustracao,
    tendencia,
} from './formatar';

describe('formatarCusto', () => {
    it('mostra dólar com duas casas em pt-BR', () => {
        expect(formatarCusto(0.18)).toBe('US$ 0,18');
        expect(formatarCusto(34.62)).toBe('US$ 34,62');
        expect(formatarCusto(1234.5)).toBe('US$ 1.234,50');
        expect(formatarCusto(0)).toBe('US$ 0,00');
    });

    it('mostra "< US$ 0,01" para valores positivos abaixo de um centavo', () => {
        expect(formatarCusto(0.005)).toBe('< US$ 0,01');
        expect(formatarCusto(0.0001)).toBe('< US$ 0,01');
    });

    it('mostra traço quando não há valor', () => {
        expect(formatarCusto(null)).toBe('—');
    });
});

describe('formatarPct', () => {
    it('mostra inteiro sem casas decimais e uma casa quando precisa', () => {
        expect(formatarPct(81)).toBe('81%');
        expect(formatarPct(81.5)).toBe('81,5%');
        expect(formatarPct(0)).toBe('0%');
    });

    it('mostra traço quando o denominador foi zero', () => {
        expect(formatarPct(null)).toBe('—');
    });
});

describe('formatarNumero', () => {
    it('separa milhar com ponto', () => {
        expect(formatarNumero(1284)).toBe('1.284');
        expect(formatarNumero(7)).toBe('7');
    });
});

describe('formatarData', () => {
    it('mostra dia e mês de uma data YYYY-MM-DD', () => {
        expect(formatarData('2026-10-15')).toBe('15/10');
    });

    it('mostra traço para nulo ou formato que não é data', () => {
        expect(formatarData(null)).toBe('—');
        expect(formatarData('15/10/2026')).toBe('—');
    });
});

describe('formatarInstante', () => {
    it('mostra dia, mês e hora no fuso de São Paulo', () => {
        expect(formatarInstante('2026-10-15T17:02:00Z')).toBe('15/10, 14:02');
        expect(formatarInstante(null)).toBe('—');
        expect(formatarInstante('não é data')).toBe('—');
    });
});

describe('hojeNoFuso e dataDeHoje', () => {
    it('usa o dia de São Paulo, não o de UTC', () => {
        // 01h de 16/10 em UTC ainda é 22h de 15/10 em São Paulo.
        const agora = new Date('2026-10-16T01:00:00Z');

        expect(hojeNoFuso(agora)).toBe('2026-10-15');
        expect(dataDeHoje(agora)).toBe('Quinta-feira, 15 de outubro');
    });
});

describe('diasEntre', () => {
    it('conta os dias da data até a referência, com sinal', () => {
        expect(diasEntre('2026-09-29', '2026-10-15')).toBe(16);
        expect(diasEntre('2026-10-16', '2026-10-15')).toBe(-1);
        expect(Number.isNaN(diasEntre('ontem', '2026-10-15'))).toBe(true);
    });
});

describe('diaRelativo', () => {
    const hoje = '2026-10-15';

    it('diz hoje e ontem', () => {
        expect(diaRelativo('2026-10-15', hoje)).toBe('hoje');
        expect(diaRelativo('2026-10-14', hoje)).toBe('ontem');
    });

    it('conta os dias até um mês e depois mostra a data', () => {
        expect(diaRelativo('2026-09-29', hoje)).toBe('há 16 dias');
        expect(diaRelativo('2026-08-01', hoje)).toBe('01/08');
    });

    it('mostra traço para nulo', () => {
        expect(diaRelativo(null, hoje)).toBe('—');
    });
});

describe('tendencia', () => {
    it('mostra a variação com sinal', () => {
        expect(tendencia(118, 100)).toEqual({ texto: '+18%', sentido: 'sobe' });
        expect(tendencia(94, 100)).toEqual({ texto: '−6%', sentido: 'desce' });
    });

    it('mostra igual quando não muda ou muda menos que um por cento', () => {
        expect(tendencia(5, 5)).toEqual({ texto: '=', sentido: 'igual' });
        expect(tendencia(1000, 1001)).toEqual({ texto: '=', sentido: 'igual' });
        expect(tendencia(0, 0)).toEqual({ texto: '=', sentido: 'igual' });
    });

    it('sem base de comparação, diz novo em vez de dividir por zero', () => {
        expect(tendencia(3, 0)).toEqual({ texto: 'novo', sentido: 'sobe' });
    });
});

describe('rotuloDaFrustracao', () => {
    it('traduz a nota de 0 a 3 em quatro níveis', () => {
        expect(rotuloDaFrustracao(0)).toBe('Calma');
        expect(rotuloDaFrustracao(0.74)).toBe('Calma');
        expect(rotuloDaFrustracao(0.75)).toBe('Neutra');
        expect(rotuloDaFrustracao(1.49)).toBe('Neutra');
        expect(rotuloDaFrustracao(1.5)).toBe('Frustrada, mas educada');
        expect(rotuloDaFrustracao(2.24)).toBe('Frustrada, mas educada');
        expect(rotuloDaFrustracao(2.25)).toBe('Muito frustrada');
        expect(rotuloDaFrustracao(3)).toBe('Muito frustrada');
    });
});
