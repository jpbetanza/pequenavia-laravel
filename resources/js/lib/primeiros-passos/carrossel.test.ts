import { describe, expect, it } from 'vite-plus/test';
import { cardDoDeslize, navegar } from './carrossel';

describe('navegar', () => {
    it('diz o lado de entrada e ignora o que sai dos limites', () => {
        expect(navegar(0, 1, 3)).toEqual({ card: 1, direcao: 'avancar' });
        expect(navegar(2, 0, 3)).toEqual({ card: 0, direcao: 'voltar' });
        expect(navegar(0, -1, 3)).toBeNull();
        expect(navegar(2, 3, 3)).toBeNull();
        expect(navegar(1, 1, 3)).toBeNull();
    });
});

describe('cardDoDeslize', () => {
    it('avança para a esquerda, volta para a direita e ignora toques curtos ou verticais', () => {
        expect(cardDoDeslize(1, -80, 5)).toBe(2);
        expect(cardDoDeslize(1, 80, 5)).toBe(0);
        expect(cardDoDeslize(1, -30, 0)).toBe(1);
        expect(cardDoDeslize(1, -60, 120)).toBe(1);
    });
});
