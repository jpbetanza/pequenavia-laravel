import { describe, expect, it } from 'vite-plus/test';
import {
    datasDaRecorrencia,
    LIMITE_DE_OCORRENCIAS,
    resumoDaRecorrencia,
} from './recorrencia';

describe('datasDaRecorrencia', () => {
    it('sem repetição devolve só a data inicial', () => {
        expect(datasDaRecorrencia('2026-10-10', 'nao', '2026-12-31')).toEqual([
            '2026-10-10',
        ]);
    });

    it('sem data final devolve só a data inicial', () => {
        expect(datasDaRecorrencia('2026-10-10', 'semanal', null)).toEqual([
            '2026-10-10',
        ]);
    });

    it('semanal repete de 7 em 7 dias até a data final', () => {
        expect(
            datasDaRecorrencia('2026-10-10', 'semanal', '2026-10-31'),
        ).toEqual(['2026-10-10', '2026-10-17', '2026-10-24', '2026-10-31']);
    });

    it('mensal repete o 2º sábado de cada mês', () => {
        // Out: 10 (2º sábado). Nov: 14 (sábados 7 e 14). Dez: 12 (sábados 5 e 12).
        expect(
            datasDaRecorrencia('2026-10-10', 'mensal', '2026-12-12'),
        ).toEqual(['2026-10-10', '2026-11-14', '2026-12-12']);
    });

    it('mensal pula o mês que não tem o 5º dia da semana', () => {
        // 29/10/2026 é quinta e a 5ª quinta de outubro. Novembro não tem 5ª quinta; dezembro tem (dia 31).
        expect(
            datasDaRecorrencia('2026-10-29', 'mensal', '2026-12-31'),
        ).toEqual(['2026-10-29', '2026-12-31']);
    });

    it('a data final entra quando cai no próprio dia', () => {
        expect(
            datasDaRecorrencia('2026-10-10', 'semanal', '2026-10-17'),
        ).toEqual(['2026-10-10', '2026-10-17']);
    });

    it('limita a série a 52 ocorrências, como o servidor', () => {
        const semanais = datasDaRecorrencia(
            '2026-10-10',
            'semanal',
            '2040-01-01',
        );
        const mensais = datasDaRecorrencia(
            '2026-10-10',
            'mensal',
            '2040-01-01',
        );

        expect(semanais).toHaveLength(LIMITE_DE_OCORRENCIAS);
        expect(semanais.at(-1)).toBe('2027-10-02');
        expect(mensais).toHaveLength(LIMITE_DE_OCORRENCIAS);
    });
});

describe('resumoDaRecorrencia', () => {
    it('mostra que não repete', () => {
        expect(resumoDaRecorrencia('2026-10-10', 'nao', null)).toBe(
            'Não repete',
        );
    });

    it('resume a mensal com o n-ésimo dia da semana, a data final e o número de datas', () => {
        expect(resumoDaRecorrencia('2026-10-10', 'mensal', '2026-12-12')).toBe(
            'No 2º sábado de cada mês, até 12/12/2026 · 3 datas',
        );
    });

    it('resume a semanal', () => {
        expect(resumoDaRecorrencia('2026-10-10', 'semanal', '2026-10-17')).toBe(
            'Toda semana, até 17/10/2026 · 2 datas',
        );
    });

    it('pede a data final quando ela falta', () => {
        expect(resumoDaRecorrencia('2026-10-10', 'mensal', null)).toBe(
            'Escolha até quando repete',
        );
    });
});
