import { describe, expect, it } from 'vite-plus/test';
import { rotuloDeRecebida } from './rotulo-de-recebida';

const agora = new Date('2026-10-10T18:00:00Z');

describe('rotuloDeRecebida', () => {
    it('usa o tempo relativo em pt-BR', () => {
        expect(rotuloDeRecebida('2026-10-10T16:00:00Z', agora)).toBe(
            'Recebida há 2 horas',
        );
    });

    it('recém-chegada diz "agora mesmo"', () => {
        expect(rotuloDeRecebida('2026-10-10T17:59:50Z', agora)).toBe(
            'Recebida agora mesmo',
        );
    });

    it('sem data, não há rótulo', () => {
        expect(rotuloDeRecebida(null, agora)).toBeNull();
    });
});
