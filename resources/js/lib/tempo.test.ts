import { describe, expect, it } from 'vite-plus/test';
import { tempoRelativo } from './tempo';

const agora = new Date('2026-10-10T12:00:00Z');

describe('tempoRelativo', () => {
    it('retorna null sem data ou com data inválida', () => {
        expect(tempoRelativo(null, agora)).toBeNull();
        expect(tempoRelativo('não é data', agora)).toBeNull();
    });

    it('mostra "agora mesmo" para menos de um minuto', () => {
        expect(tempoRelativo('2026-10-10T11:59:30Z', agora)).toBe(
            'agora mesmo',
        );
    });

    it('usa a maior unidade que cabe, no passado', () => {
        expect(tempoRelativo('2026-10-10T11:00:00Z', agora)).toBe('há 1 hora');
        expect(tempoRelativo('2026-10-08T12:00:00Z', agora)).toBe('há 2 dias');
        expect(tempoRelativo('2026-09-19T12:00:00Z', agora)).toBe(
            'há 3 semanas',
        );
    });

    it('usa pt-BR com o texto de passado', () => {
        const texto = tempoRelativo('2026-10-09T12:00:00Z', agora);

        expect(texto).toBe('há 1 dia');
    });
});
