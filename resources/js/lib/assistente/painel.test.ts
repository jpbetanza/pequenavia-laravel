import { describe, expect, it } from 'vite-plus/test';
import {
    abertoAoCarregar,
    deveFecharAoNavegar,
    deveGravarPreferencia,
} from './painel';

describe('abertoAoCarregar', () => {
    it('reabre no desktop quando a última escolha foi deixar aberto', () => {
        expect(abertoAoCarregar('1', false)).toBe(true);
    });

    it('no celular começa fechado, mesmo com a preferência salva', () => {
        expect(abertoAoCarregar('1', true)).toBe(false);
    });

    it('começa fechado sem preferência salva ou com a preferência fechada', () => {
        expect(abertoAoCarregar(null, false)).toBe(false);
        expect(abertoAoCarregar('0', false)).toBe(false);
    });
});

describe('deveGravarPreferencia', () => {
    it('grava só no desktop, para o celular não apagar a escolha do desktop', () => {
        expect(deveGravarPreferencia(false)).toBe(true);
        expect(deveGravarPreferencia(true)).toBe(false);
    });
});

describe('deveFecharAoNavegar', () => {
    it('não fecha quando o destino é a própria página (reload)', () => {
        expect(
            deveFecharAoNavegar(
                'https://app.test/agendamentos/calendario?data=2026-10-18',
                'https://app.test/agendamentos/calendario?data=2026-10-18',
            ),
        ).toBe(false);
    });

    it('fecha quando o destino tem outro caminho', () => {
        expect(
            deveFecharAoNavegar(
                'https://app.test/agendamentos/calendario',
                'https://app.test/agendamentos/solicitacoes',
            ),
        ).toBe(true);
    });

    it('compara caminhos relativos também', () => {
        expect(
            deveFecharAoNavegar(
                '/agendamentos/calendario',
                '/agendamentos/calendario?x=1',
            ),
        ).toBe(false);
        expect(
            deveFecharAoNavegar(
                '/agendamentos/calendario',
                '/agendamentos/solicitacoes',
            ),
        ).toBe(true);
    });
});
