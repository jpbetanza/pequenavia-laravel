import { describe, expect, it } from 'vite-plus/test';
import {
    escalaDoPreview,
    limitarPosicao,
    moverCampo,
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
