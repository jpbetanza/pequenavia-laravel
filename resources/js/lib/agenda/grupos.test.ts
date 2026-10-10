import { describe, expect, it } from 'vite-plus/test';
import { filtrarGrupos } from './grupos';

const grupos = [
    {
        id: '1',
        nome: 'Catequese',
        coordenacao: 'Irmã Lúcia',
        contato: '(11) 9 0000-0000',
    },
    {
        id: '2',
        nome: 'Pastoral Familiar',
        coordenacao: 'Marcos e Helena',
        contato: 'familiar@paroquia.org.br',
    },
    {
        id: '3',
        nome: 'Coral Santa Cecília',
        coordenacao: null,
        contato: null,
    },
];

describe('filtrarGrupos', () => {
    it('devolve todos os grupos quando a busca está vazia ou só tem espaços', () => {
        expect(filtrarGrupos(grupos, '')).toEqual(grupos);
        expect(filtrarGrupos(grupos, '   ')).toEqual(grupos);
    });

    it('acha pelo nome ignorando acento e caixa', () => {
        expect(filtrarGrupos(grupos, 'PASTORAL').map((g) => g.id)).toEqual([
            '2',
        ]);
        expect(filtrarGrupos(grupos, 'cecilia').map((g) => g.id)).toEqual([
            '3',
        ]);
    });

    it('acha pela coordenação e pelo contato', () => {
        expect(filtrarGrupos(grupos, 'helena').map((g) => g.id)).toEqual(['2']);
        expect(filtrarGrupos(grupos, 'familiar@').map((g) => g.id)).toEqual([
            '2',
        ]);
    });

    it('devolve vazio quando nada corresponde, sem quebrar com campos nulos', () => {
        expect(filtrarGrupos(grupos, 'xyz')).toEqual([]);
    });
});
