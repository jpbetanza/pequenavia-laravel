import { describe, expect, it } from 'vite-plus/test';
import { filtrarPorTitulo, normalizarBusca } from './busca';

const modelos = [
    { id: '1', title: 'Batismo' },
    { id: '2', title: 'Primeira Eucaristia' },
    { id: '3', title: 'Crisma — Catedral' },
    { id: '4', title: 'Matrimônio' },
];

describe('normalizarBusca', () => {
    it('remove acento, põe em minúsculas e apara espaços', () => {
        expect(normalizarBusca('  Matrimônio ')).toBe('matrimonio');
        expect(normalizarBusca('AÇÃO')).toBe('acao');
    });
});

describe('filtrarPorTitulo', () => {
    it('devolve todos os itens com termo vazio ou só espaços', () => {
        expect(filtrarPorTitulo(modelos, '')).toEqual(modelos);
        expect(filtrarPorTitulo(modelos, '   ')).toEqual(modelos);
    });

    it('acha pelo título sem diferenciar caixa', () => {
        expect(filtrarPorTitulo(modelos, 'PRIMEIRA').map((m) => m.id)).toEqual([
            '2',
        ]);
    });

    it('acha sem acento no termo e com acento no título', () => {
        expect(
            filtrarPorTitulo(modelos, 'matrimonio').map((m) => m.id),
        ).toEqual(['4']);
    });

    it('devolve lista vazia quando nada casa', () => {
        expect(filtrarPorTitulo(modelos, 'xyz')).toEqual([]);
    });
});
