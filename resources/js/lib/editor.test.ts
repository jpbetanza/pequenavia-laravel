import { describe, expect, it } from 'vite-plus/test';
import type { Campo } from '@/types/certificados';
import {
    deslocamentoDaTecla,
    deslocamentoDoEvento,
    deslocarPosicao,
    escalaDoPreview,
    limitarPosicao,
    moverCampo,
    mudancasDiferentes,
    retirarMudancas,
    reverterCampo,
    valoresDoNovoCampo,
} from './editor';

describe('escalaDoPreview', () => {
    it('divide a largura mostrada pela largura natural da imagem', () => {
        expect(escalaDoPreview(500, 2000)).toBe(0.25);
    });

    it('usa 1 quando ainda não há medidas', () => {
        expect(escalaDoPreview(0, 2000)).toBe(1);
        expect(escalaDoPreview(500, 0)).toBe(1);
    });
});

describe('limitarPosicao', () => {
    it('arredonda e mantém a posição entre 0 e 10000', () => {
        expect(limitarPosicao(10.6)).toBe(11);
        expect(limitarPosicao(-5)).toBe(0);
        expect(limitarPosicao(20000)).toBe(10000);
    });
});

describe('moverCampo', () => {
    it('converte o deslocamento da tela para pixels da imagem original', () => {
        expect(
            moverCampo({ x: 100, y: 200 }, { dx: 50, dy: -25 }, 0.5, false),
        ).toEqual({ x: 200, y: 150 });
    });

    it('só move na vertical quando o campo é centralizado', () => {
        expect(
            moverCampo({ x: 100, y: 200 }, { dx: 50, dy: 10 }, 0.5, true),
        ).toEqual({ y: 220 });
    });

    it('não deixa o campo sair da imagem pela esquerda ou pelo topo', () => {
        expect(
            moverCampo({ x: 10, y: 10 }, { dx: -500, dy: -500 }, 1, false),
        ).toEqual({ x: 0, y: 0 });
    });
});

describe('valoresDoNovoCampo', () => {
    it('herda o estilo do último campo e começa em 50, 50', () => {
        const ultimo = {
            id: 'a',
            modelo_id: 'm',
            nome: 'Nome',
            label: null,
            x: 10,
            y: 20,
            cor: '#ffffff',
            tamanho_fonte: 80,
            fonte_familia: 'Cinzel',
            peso_fonte: 700,
            espacamento_letras: 2,
            centralizado: true,
        };

        expect(valoresDoNovoCampo(ultimo)).toEqual({
            x: 50,
            y: 50,
            cor: '#ffffff',
            tamanho_fonte: 80,
            fonte_familia: 'Cinzel',
            peso_fonte: 700,
            espacamento_letras: 2,
            centralizado: true,
        });
    });

    it('usa os padrões quando não há campo anterior', () => {
        expect(valoresDoNovoCampo(undefined)).toEqual({
            x: 50,
            y: 50,
            cor: '#000000',
            tamanho_fonte: 24,
            fonte_familia: 'Georgia',
            peso_fonte: 600,
            espacamento_letras: 0,
            centralizado: false,
        });
    });
});

describe('reverterCampo', () => {
    const base: Campo = {
        id: 'a',
        modelo_id: 'm',
        nome: 'Nome',
        label: null,
        x: 10,
        y: 20,
        cor: '#111111',
        tamanho_fonte: 24,
        fonte_familia: 'Georgia',
        peso_fonte: 600,
        espacamento_letras: 0,
        centralizado: false,
    };

    it('volta só os campos da falha que ainda têm o valor otimista', () => {
        const atual = { ...base, x: 99, y: 88 };

        expect(reverterCampo(atual, { x: 99 }, base)).toMatchObject({
            x: 10,
            y: 88,
        });
    });

    it('não sobrescreve um campo alterado depois da chamada que falhou', () => {
        const atual = { ...base, x: 99, y: 300 };

        expect(reverterCampo(atual, { x: 99, y: 50 }, base)).toMatchObject({
            x: 10,
            y: 300,
        });
    });
});

describe('deslocamentoDaTecla', () => {
    it('mapeia as setas para 1 px da imagem', () => {
        expect(deslocamentoDaTecla('ArrowLeft', false)).toEqual({
            dx: -1,
            dy: 0,
        });
        expect(deslocamentoDaTecla('ArrowRight', false)).toEqual({
            dx: 1,
            dy: 0,
        });
        expect(deslocamentoDaTecla('ArrowUp', false)).toEqual({
            dx: 0,
            dy: -1,
        });
        expect(deslocamentoDaTecla('ArrowDown', false)).toEqual({
            dx: 0,
            dy: 1,
        });
    });

    it('usa 10 px com Shift', () => {
        expect(deslocamentoDaTecla('ArrowDown', true)).toEqual({
            dx: 0,
            dy: 10,
        });
    });

    it('ignora as outras teclas', () => {
        expect(deslocamentoDaTecla('a', false)).toBeNull();
        expect(deslocamentoDaTecla('Enter', true)).toBeNull();
    });
});

describe('deslocarPosicao', () => {
    it('move nos dois eixos quando o campo é livre', () => {
        expect(
            deslocarPosicao({ x: 100, y: 200 }, false, { dx: 10, dy: -10 }),
        ).toEqual({
            x: 110,
            y: 190,
        });
    });

    it('não mexe em x quando o campo é centralizado', () => {
        expect(
            deslocarPosicao({ x: 100, y: 200 }, true, { dx: 10, dy: 1 }),
        ).toEqual({
            y: 201,
        });
    });

    it('não passa dos limites da imagem', () => {
        expect(
            deslocarPosicao({ x: 0, y: 10000 }, false, { dx: -1, dy: 10 }),
        ).toEqual({
            x: 0,
            y: 10000,
        });
    });
});

describe('deslocamentoDoEvento', () => {
    const tecla = {
        key: 'ArrowRight',
        shiftKey: false,
        ctrlKey: false,
        metaKey: false,
        altKey: false,
    };

    it('mexe o campo com a seta sem modificadores', () => {
        expect(deslocamentoDoEvento(tecla)).toEqual({ dx: 1, dy: 0 });
    });

    it('usa 10 px com Shift', () => {
        expect(deslocamentoDoEvento({ ...tecla, shiftKey: true })).toEqual({
            dx: 10,
            dy: 0,
        });
    });

    it('ignora Ctrl, Cmd e Alt para não atrapalhar atalhos do navegador', () => {
        expect(deslocamentoDoEvento({ ...tecla, ctrlKey: true })).toBeNull();
        expect(deslocamentoDoEvento({ ...tecla, metaKey: true })).toBeNull();
        expect(deslocamentoDoEvento({ ...tecla, altKey: true })).toBeNull();
        expect(
            deslocamentoDoEvento({ ...tecla, shiftKey: true, ctrlKey: true }),
        ).toBeNull();
    });
});

describe('retirarMudancas', () => {
    it('tira do pendente as chaves que a outra mudança passou a definir', () => {
        expect(retirarMudancas({ x: 120, y: 80 }, { x: 500 })).toEqual({
            y: 80,
        });
    });

    it('deixa o pendente intacto quando a mudança não toca nas suas chaves', () => {
        expect(retirarMudancas({ x: 120 }, { nome: 'Novo' })).toEqual({
            x: 120,
        });
    });

    it('tira x quando o campo passa a ser centralizado', () => {
        expect(
            retirarMudancas({ x: 120, y: 80 }, { centralizado: true }),
        ).toEqual({ y: 80 });
    });

    it('não mexe no pendente original', () => {
        const pendentes = { x: 120, y: 80 };

        retirarMudancas(pendentes, { y: 1 });

        expect(pendentes).toEqual({ x: 120, y: 80 });
    });
});

describe('mudancasDiferentes', () => {
    const confirmado: Campo = {
        id: 'a',
        modelo_id: 'm',
        nome: 'Nome',
        label: null,
        x: 10,
        y: 20,
        cor: '#111111',
        tamanho_fonte: 24,
        fonte_familia: 'Georgia',
        peso_fonte: 600,
        espacamento_letras: 0,
        centralizado: false,
    };

    it('devolve só as chaves que mudaram em relação ao confirmado', () => {
        expect(mudancasDiferentes({ x: 10, y: 99 }, confirmado)).toEqual({
            y: 99,
        });
    });

    it('fica vazio quando o pendente voltou à posição confirmada', () => {
        expect(mudancasDiferentes({ x: 10, y: 20 }, confirmado)).toEqual({});
    });
});
