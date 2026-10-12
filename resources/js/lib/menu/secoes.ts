import type { InertiaLinkProps } from '@inertiajs/react';
import { calendario } from '@/routes/agendamentos';
import { index as equipamentos } from '@/routes/agendamentos/equipamentos';
import { index as espacos } from '@/routes/agendamentos/espacos';
import { index as grupos } from '@/routes/agendamentos/grupos';
import { index as solicitacoes } from '@/routes/agendamentos/solicitacoes';
import { index as historico } from '@/routes/historico';
import { index as meusCertificados } from '@/routes/meus-certificados';

export type ItemDoMenu = {
    titulo: string;
    descricao: string;
    href: NonNullable<InertiaLinkProps['href']>;
    /** Número no item; some quando é 0. */
    contador?: number;
};

export type SecaoDoMenu = {
    id: 'agendamentos' | 'certificados';
    titulo: string;
    /** Prefixos de caminho que pertencem à seção (sem domínio, começando com "/"). */
    prefixos: string[];
    itens: ItemDoMenu[];
};

export type IdDaAba = 'inicio' | SecaoDoMenu['id'];

/** Tira a query string, o fragmento e as barras finais: "/agendamentos/" vira "/agendamentos". */
function caminhoNormalizado(caminho: string): string {
    const semQuery = caminho.split(/[?#]/u)[0];

    return semQuery.length > 1 ? semQuery.replace(/\/+$/u, '') : semQuery;
}

/** Aba ativa para um caminho ("/agendamentos/espacos" → "agendamentos"). Fora das seções: null. */
export function abaAtiva(
    caminho: string,
    secoes: SecaoDoMenu[],
): IdDaAba | null {
    const alvo = caminhoNormalizado(caminho);

    if (alvo === '/inicio') {
        return 'inicio';
    }

    const secao = secoes.find((candidata) =>
        candidata.prefixos.some(
            (prefixo) => alvo === prefixo || alvo.startsWith(`${prefixo}/`),
        ),
    );

    return secao?.id ?? null;
}

export function secoesDoMenu(solicitacoesPendentes: number): SecaoDoMenu[] {
    return [
        {
            id: 'agendamentos',
            titulo: 'Agendamentos',
            prefixos: ['/agendamentos'],
            itens: [
                {
                    titulo: 'Calendário',
                    descricao: 'A semana das salas, por espaço',
                    href: calendario(),
                },
                {
                    titulo: 'Solicitações',
                    descricao: 'Pedidos das pastorais para decidir',
                    href: solicitacoes(),
                    contador: solicitacoesPendentes,
                },
                {
                    titulo: 'Espaços',
                    descricao: 'Salas, capela, horários e bloqueios',
                    href: espacos(),
                },
                {
                    titulo: 'Equipamentos',
                    descricao: 'Projetores e o que separar hoje',
                    href: equipamentos(),
                },
                {
                    titulo: 'Grupos e pastorais',
                    descricao: 'Quem reserva e as reuniões fixas',
                    href: grupos(),
                },
            ],
        },
        {
            id: 'certificados',
            titulo: 'Certificados',
            prefixos: ['/meus-certificados', '/historico'],
            itens: [
                {
                    titulo: 'Modelos',
                    descricao: 'Artes e campos de cada sacramento',
                    href: meusCertificados(),
                },
                {
                    titulo: 'Histórico',
                    descricao: 'Certificados já emitidos',
                    href: historico(),
                },
            ],
        },
    ];
}
