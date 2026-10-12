import { describe, expect, it } from 'vite-plus/test';
import { LeitorDeSse } from './sse';

describe('LeitorDeSse', () => {
    it('lê eventos completos com nome e dados', () => {
        const leitor = new LeitorDeSse();
        expect(leitor.ler('event: texto\ndata: {"pedaco":"Oi"}\n\n')).toEqual([
            { evento: 'texto', dados: '{"pedaco":"Oi"}' },
        ]);
    });

    it('junta um evento partido entre dois pedaços sem duplicar', () => {
        const leitor = new LeitorDeSse();
        expect(leitor.ler('event: texto\ndata: {"ped')).toEqual([]);
        expect(leitor.ler('aco":"Oi"}\n\nevent: fim\ndata: {}\n\n')).toEqual([
            { evento: 'texto', dados: '{"pedaco":"Oi"}' },
            { evento: 'fim', dados: '{}' },
        ]);
    });

    it('aceita \\r\\n, ignora comentários e usa "message" sem nome', () => {
        const leitor = new LeitorDeSse();
        expect(leitor.ler(': ping\r\n\r\ndata: x\r\n\r\n')).toEqual([
            { evento: 'message', dados: 'x' },
        ]);
    });
});
