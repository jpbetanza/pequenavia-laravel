import { describe, expect, it } from 'vite-plus/test';
import { linkDoCadastro, normalizarAcao, rotuloDoSelo } from './cadastro';

describe('rotuloDoSelo', () => {
    it('pede confirmação enquanto a proposta está pendente, qualquer que seja a operação', () => {
        expect(rotuloDoSelo('proposta', 'criar')).toBe(
            'Aguardando sua confirmação',
        );
        expect(rotuloDoSelo('proposta', 'excluir')).toBe(
            'Aguardando sua confirmação',
        );
    });

    it('diz o que foi feito depois de confirmada', () => {
        expect(rotuloDoSelo('confirmada', 'criar')).toBe('Cadastrado');
        expect(rotuloDoSelo('confirmada', 'alterar')).toBe('Alterado');
        expect(rotuloDoSelo('confirmada', 'excluir')).toBe('Excluído');
    });

    it('mostra descartada e expirada sem depender da operação', () => {
        expect(rotuloDoSelo('descartada', 'alterar')).toBe('Descartada');
        expect(rotuloDoSelo('expirada', 'criar')).toBe('Expirada');
    });
});

describe('linkDoCadastro', () => {
    it('leva o espaço salvo para a tela de edição dele', () => {
        expect(linkDoCadastro('espaco', '12')).toBe(
            '/agendamentos/espacos/12/editar',
        );
    });

    it('sem id de espaço (exclusão), leva para a lista de espaços', () => {
        expect(linkDoCadastro('espaco', null)).toBe('/agendamentos/espacos');
    });

    it('grupo e equipamento vão para a lista da própria entidade', () => {
        expect(linkDoCadastro('grupo', '7')).toBe('/agendamentos/grupos');
        expect(linkDoCadastro('grupo', null)).toBe('/agendamentos/grupos');
        expect(linkDoCadastro('equipamento', '3')).toBe(
            '/agendamentos/equipamentos',
        );
    });
});

describe('normalizarAcao', () => {
    it('trata cartão antigo, sem tipo, como criar_reserva', () => {
        const antigo: Parameters<typeof normalizarAcao>[0] = {
            id: 'a1',
            status: 'proposta',
            resumo: {
                titulo: 'Festa',
                grupo_id: null,
                grupo_nome: null,
                espaco_id: 'e1',
                espaco_nome: 'Salão',
                data: '2026-10-17',
                inicio: '18:00',
                fim: '20:00',
                pessoas: null,
                equipamentos: [],
                observacoes: null,
            },
            resultado: null,
        };

        const normalizada = normalizarAcao(antigo);

        expect(normalizada.tipo).toBe('criar_reserva');
        expect(normalizada.id).toBe('a1');
    });

    it('mantém o tipo quando o cartão já traz um', () => {
        const cadastro = normalizarAcao({
            id: 'a2',
            status: 'proposta',
            tipo: 'excluir_grupo',
            cadastro: {
                entidade: 'grupo',
                operacao: 'excluir',
                titulo: 'Excluir grupo Coral',
                linhas: [],
                avisos: [],
            },
            resultado: null,
        });

        expect(cadastro.tipo).toBe('excluir_grupo');
    });
});
