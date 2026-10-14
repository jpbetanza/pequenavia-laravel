import { describe, expect, it } from 'vite-plus/test';
import type { PrimeirosPassos } from '@/types/primeiros-passos';
import {
    numeroDoPasso,
    pedidoDaDescricao,
    pedidoDoPasso,
    porcentagem,
    selosDosCadastros,
} from './roteiro';

const roteiro: PrimeirosPassos = {
    fase: 'andamento',
    feitos: 3,
    total: 5,
    atual: 'formulario',
    adiado: false,
    passos: [
        {
            chave: 'espacos',
            titulo: 'Espaços',
            descricao: '',
            feito: true,
            pulado: false,
        },
        {
            chave: 'equipamentos',
            titulo: 'Equipamentos',
            descricao: '',
            feito: true,
            pulado: false,
        },
        {
            chave: 'grupos',
            titulo: 'Grupos',
            descricao: '',
            feito: false,
            pulado: true,
        },
        {
            chave: 'formulario',
            titulo: 'Formulário',
            descricao: '',
            feito: false,
            pulado: false,
        },
        {
            chave: 'avisos',
            titulo: 'Avisos',
            descricao: '',
            feito: false,
            pulado: false,
        },
    ],
    contagens: { espacos: 3, equipamentos: 1, grupos: 0 },
    link_do_formulario: 'https://exemplo.test/reservar/abc',
};

describe('roteiro', () => {
    it('calcula a barra e a posição do passo', () => {
        expect(porcentagem(roteiro)).toBe(60);
        expect(numeroDoPasso(roteiro, 'formulario')).toBe(4);
    });

    it('resume os cadastros só com o que existe, no singular e no plural', () => {
        expect(selosDosCadastros(roteiro.contagens)).toEqual([
            '3 espaços',
            '1 equipamento',
        ]);
    });

    it('monta os pedidos ao assistente', () => {
        expect(pedidoDoPasso('espacos')).toContain('espaços');
        expect(pedidoDaDescricao('grupos', '  Coral e Catequese ')).toBe(
            'Cadastre estes grupos e pastorais: Coral e Catequese',
        );
    });
});
