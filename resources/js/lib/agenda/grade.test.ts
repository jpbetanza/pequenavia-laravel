import { describe, expect, it } from 'vite-plus/test';
import {
    ALTURA_DA_GRADE,
    colunasLadoALado,
    horaDoClique,
    posicaoDoAgora,
    posicaoDoBloco,
} from './grade';

const bloco = (id: string, inicio: string, fim: string) => ({
    id,
    inicio,
    fim,
});

describe('posicaoDoBloco', () => {
    it('posiciona um bloco simples a 48 px por hora a partir de 07:00', () => {
        expect(posicaoDoBloco('07:00', '08:00')).toEqual({
            topo: 0,
            altura: 48,
        });
        expect(posicaoDoBloco('09:00', '10:30')).toEqual({
            topo: 96,
            altura: 72,
        });
    });

    it('recorta o início antes de 07:00 e o fim depois de 22:00', () => {
        expect(posicaoDoBloco('06:00', '08:00')).toEqual({
            topo: 0,
            altura: 48,
        });
        expect(posicaoDoBloco('21:00', '23:30')).toEqual({
            topo: 672,
            altura: 48,
        });
    });

    it('devolve altura zero quando o bloco fica todo fora do intervalo', () => {
        expect(posicaoDoBloco('05:00', '06:30').altura).toBe(0);
        expect(posicaoDoBloco('22:30', '23:00').altura).toBe(0);
    });

    it('cobre a grade inteira de 07:00 a 22:00', () => {
        expect(posicaoDoBloco('07:00', '22:00').altura).toBe(ALTURA_DA_GRADE);
        expect(ALTURA_DA_GRADE).toBe(720);
    });
});

describe('colunasLadoALado', () => {
    it('um bloco sozinho ocupa a largura toda', () => {
        expect(
            colunasLadoALado([bloco('a', '09:00', '10:00')]).get('a'),
        ).toEqual({
            coluna: 0,
            totalColunas: 1,
        });
    });

    it('dois blocos sobrepostos dividem o dia em duas colunas', () => {
        const posicoes = colunasLadoALado([
            bloco('a', '09:00', '11:00'),
            bloco('b', '10:00', '12:00'),
        ]);

        expect(posicoes.get('a')).toEqual({ coluna: 0, totalColunas: 2 });
        expect(posicoes.get('b')).toEqual({ coluna: 1, totalColunas: 2 });
    });

    it('três blocos em cluster usam três colunas para todos', () => {
        const posicoes = colunasLadoALado([
            bloco('a', '09:00', '12:00'),
            bloco('b', '10:00', '11:00'),
            bloco('c', '10:30', '11:30'),
        ]);

        expect(posicoes.get('a')).toEqual({ coluna: 0, totalColunas: 3 });
        expect(posicoes.get('b')).toEqual({ coluna: 1, totalColunas: 3 });
        expect(posicoes.get('c')).toEqual({ coluna: 2, totalColunas: 3 });
    });

    it('reaproveita a coluna de um bloco que já terminou dentro do mesmo cluster', () => {
        const posicoes = colunasLadoALado([
            bloco('a', '09:00', '10:00'),
            bloco('b', '09:30', '11:00'),
            bloco('c', '10:00', '11:00'),
        ]);

        expect(posicoes.get('c')).toEqual({ coluna: 0, totalColunas: 2 });
    });

    it('blocos sequenciais não dividem coluna', () => {
        const posicoes = colunasLadoALado([
            bloco('a', '09:00', '10:00'),
            bloco('b', '10:00', '11:00'),
        ]);

        expect(posicoes.get('a')).toEqual({ coluna: 0, totalColunas: 1 });
        expect(posicoes.get('b')).toEqual({ coluna: 0, totalColunas: 1 });
    });

    it('a borda 10–12 e 12–13 não conta como sobreposição', () => {
        const posicoes = colunasLadoALado([
            bloco('a', '10:00', '12:00'),
            bloco('b', '12:00', '13:00'),
        ]);

        expect(posicoes.get('a')).toEqual({ coluna: 0, totalColunas: 1 });
        expect(posicoes.get('b')).toEqual({ coluna: 0, totalColunas: 1 });
    });

    it('grupos separados no mesmo dia são calculados cada um por si', () => {
        const posicoes = colunasLadoALado([
            bloco('a', '08:00', '10:00'),
            bloco('b', '09:00', '11:00'),
            bloco('c', '14:00', '15:00'),
        ]);

        expect(posicoes.get('a')?.totalColunas).toBe(2);
        expect(posicoes.get('c')).toEqual({ coluna: 0, totalColunas: 1 });
    });
});

describe('posicaoDoAgora', () => {
    it('posiciona a linha dentro do horário da grade', () => {
        expect(posicaoDoAgora('14:30')).toBe(360);
        expect(posicaoDoAgora('07:00')).toBe(0);
    });

    it('não mostra a linha fora de 07:00–22:00', () => {
        expect(posicaoDoAgora('06:59')).toBeNull();
        expect(posicaoDoAgora('22:00')).toBeNull();
    });
});

describe('horaDoClique', () => {
    it('arredonda para 30 minutos a partir de 07:00', () => {
        expect(horaDoClique(0)).toBe('07:00');
        expect(horaDoClique(96)).toBe('09:00');
        expect(horaDoClique(100)).toBe('09:00');
        expect(horaDoClique(120)).toBe('09:30');
    });

    it('limita o início a 07:00 e a 21:30', () => {
        expect(horaDoClique(-50)).toBe('07:00');
        expect(horaDoClique(99999)).toBe('21:30');
    });
});
