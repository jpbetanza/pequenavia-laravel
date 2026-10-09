import { describe, expect, it } from 'vite-plus/test';
import { formatarData, ordenarValores, resumoDosValores } from './historico';

describe('ordenarValores', () => {
    it('põe primeiro os campos na ordem do modelo e depois os demais, em ordem alfabética', () => {
        const valores = { Zeta: '3', Data: 'b', Nome: 'a', Alfa: '4' };

        expect(ordenarValores(valores, ['Nome', 'Data'])).toEqual([
            ['Nome', 'a'],
            ['Data', 'b'],
            ['Alfa', '4'],
            ['Zeta', '3'],
        ]);
    });

    it('ordena alfabeticamente quando o modelo foi excluído', () => {
        expect(ordenarValores({ B: '2', A: '1' }, [])).toEqual([
            ['A', '1'],
            ['B', '2'],
        ]);
    });

    it('trata uma lista vazia, que é como o PHP serializa um array vazio', () => {
        expect(
            ordenarValores([] as unknown as Record<string, string>, []),
        ).toEqual([]);
    });
});

describe('resumoDosValores', () => {
    it('junta os 3 primeiros com " · "', () => {
        const entradas: [string, string][] = [
            ['A', '1'],
            ['B', '2'],
            ['C', '3'],
            ['D', '4'],
        ];

        expect(resumoDosValores(entradas)).toBe('A: 1 · B: 2 · C: 3');
    });
});

describe('formatarData', () => {
    it('formata em português, com dia, mês, ano, hora e minuto', () => {
        expect(formatarData('2026-10-09T15:04:00Z', 'UTC')).toBe(
            '09/10/2026, 15:04',
        );
    });
});
