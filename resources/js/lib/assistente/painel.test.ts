import { describe, expect, it } from 'vite-plus/test';
import { abertoAoCarregar, deveGravarPreferencia } from './painel';

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
