import { describe, expect, it } from 'vite-plus/test';
import { abaAtiva, secoesDoMenu, type SecaoDoMenu } from './secoes';
import { toUrl } from '@/lib/utils';

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

describe('secoesDoMenu', () => {
    it('cada item de uma seção cai dentro de um prefixo dela', () => {
        for (const secao of secoesDoMenu(0)) {
            for (const item of secao.itens) {
                const caminho = toUrl(item.href);

                expect(
                    secao.prefixos.some(
                        (prefixo) =>
                            caminho === prefixo ||
                            caminho.startsWith(`${prefixo}/`),
                    ),
                ).toBe(true);
            }
        }
    });

    it('leva o contador de solicitações pendentes ao item certo', () => {
        const [agendamentos] = secoesDoMenu(3);
        const solicitacoes = agendamentos.itens.find(
            (item) => item.titulo === 'Solicitações',
        );

        expect(solicitacoes?.contador).toBe(3);
    });
});
