import { describe, expect, it } from 'vite-plus/test';
import { abaAtiva, type SecaoDoMenu } from './secoes';

const secoes: SecaoDoMenu[] = [
    {
        id: 'agendamentos',
        titulo: 'Agendamentos',
        prefixos: ['/agendamentos'],
        itens: [],
    },
    {
        id: 'certificados',
        titulo: 'Certificados',
        prefixos: ['/meus-certificados', '/historico'],
        itens: [],
    },
];

describe('abaAtiva', () => {
    it('reconhece o início', () => {
        expect(abaAtiva('/inicio', secoes)).toBe('inicio');
        expect(abaAtiva('/inicio?pergunta=1', secoes)).toBe('inicio');
    });

    it('reconhece subpáginas da seção', () => {
        expect(abaAtiva('/agendamentos/espacos/01abc/editar', secoes)).toBe(
            'agendamentos',
        );
        expect(abaAtiva('/agendamentos/', secoes)).toBe('agendamentos');
        expect(abaAtiva('/meus-certificados/01abc/campos', secoes)).toBe(
            'certificados',
        );
        expect(abaAtiva('/historico?pagina=2', secoes)).toBe('certificados');
    });

    it('não confunde prefixo parecido', () => {
        expect(abaAtiva('/agendamentosx', secoes)).toBeNull();
        expect(abaAtiva('/settings/profile', secoes)).toBeNull();
    });
});
