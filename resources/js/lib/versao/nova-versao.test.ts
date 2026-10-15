import { beforeEach, describe, expect, it } from 'vite-plus/test';
import {
    adiar,
    ehVersaoNova,
    lerEstado,
    reabrir,
    reiniciarParaTestes,
    verificar,
} from './nova-versao';

function resposta(corpo: unknown, ok = true) {
    return (async () => ({
        ok,
        json: async () => corpo,
    })) as unknown as typeof fetch;
}

describe('nova versão', () => {
    beforeEach(() => reiniciarParaTestes());

    it('só conta como nova uma versão diferente e não vazia', () => {
        expect(ehVersaoNova('abc', 'def')).toBe(true);
        expect(ehVersaoNova('abc', 'abc')).toBe(false);
        expect(ehVersaoNova('abc', '')).toBe(false);
        expect(ehVersaoNova('abc', null)).toBe(false);
    });

    it('marca a versão nova quando o servidor responde outra', async () => {
        await verificar('abc', resposta({ versao: 'abc' }));
        expect(lerEstado().nova).toBe(false);

        await verificar('abc', resposta({ versao: 'def' }));
        expect(lerEstado().nova).toBe(true);
    });

    it('erro do servidor ou sem rede não muda nada', async () => {
        await verificar('abc', resposta({ versao: 'def' }, false));
        await verificar('abc', (async () => {
            throw new Error('offline');
        }) as unknown as typeof fetch);

        expect(lerEstado().nova).toBe(false);
    });

    it('"Depois" esconde o aviso e o lembrete reabre', () => {
        adiar();
        expect(lerEstado().adiado).toBe(true);

        reabrir();
        expect(lerEstado().adiado).toBe(false);
    });
});
