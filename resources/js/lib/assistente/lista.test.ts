import { describe, expect, it } from 'vite-plus/test';
import type { ResumoDeConversa } from '@/types/assistente';
import { agruparConversas, filtrarConversas } from './lista';

const AGORA = '2026-10-15T15:00:00-03:00';

const conversa = (
    id: string,
    titulo: string,
    ultimaMensagemEm: string | null,
): ResumoDeConversa => ({
    id,
    titulo,
    previa: null,
    ultima_mensagem_em: ultimaMensagemEm,
});

describe('agruparConversas', () => {
    it('separa em Hoje, Esta semana e Mais antigas, preservando a ordem interna', () => {
        const conversas = [
            conversa('c1', 'Hoje cedo', '2026-10-15T10:00:00-03:00'),
            conversa('c2', 'Terça', '2026-10-13T10:00:00-03:00'),
            conversa('c3', 'Sábado passado', '2026-10-10T10:00:00-03:00'),
            conversa('c4', 'Sem mensagens', null),
            conversa('c5', 'Hoje depois', '2026-10-15T14:00:00-03:00'),
        ];

        expect(agruparConversas(conversas, AGORA)).toEqual([
            { rotulo: 'Hoje', conversas: [conversas[0], conversas[4]] },
            { rotulo: 'Esta semana', conversas: [conversas[1]] },
            { rotulo: 'Mais antigas', conversas: [conversas[2], conversas[3]] },
        ]);
    });

    it('usa o dia de São Paulo, não o de UTC, na fronteira da meia-noite', () => {
        // Agora é 15/10 às 20:30 em SP (23:30Z, ainda dia 15 em UTC). A conversa é de 14/10 às 22:30 em SP
        // (01:30Z de 15/10): é "Esta semana". Em UTC seria dia 15 = hoje, então um código em UTC falharia aqui.
        const conversas = [
            conversa('c1', 'Noite de quarta', '2026-10-15T01:30:00Z'),
        ];

        expect(agruparConversas(conversas, '2026-10-15T23:30:00Z')).toEqual([
            { rotulo: 'Esta semana', conversas },
        ]);
    });

    it('um domingo à noite em São Paulo cai em "Mais antigas" para uma busca na semana seguinte', () => {
        // 2026-10-12T02:00Z é domingo 11/10 às 23:00 em São Paulo, então é de outra semana (começa na segunda 12/10).
        const conversas = [
            conversa('c1', 'Domingo à noite', '2026-10-12T02:00:00Z'),
        ];

        expect(agruparConversas(conversas, AGORA)).toEqual([
            { rotulo: 'Mais antigas', conversas },
        ]);
    });

    it('não devolve grupos vazios', () => {
        const conversas = [
            conversa('c1', 'Antiga', '2026-09-01T10:00:00-03:00'),
        ];

        expect(agruparConversas(conversas, AGORA)).toEqual([
            { rotulo: 'Mais antigas', conversas },
        ]);
        expect(agruparConversas([], AGORA)).toEqual([]);
    });
});

describe('filtrarConversas', () => {
    const conversas = [
        conversa('c1', 'Salão no sábado', null),
        conversa('c2', 'Certificados de outubro', null),
    ];

    it('ignora acento e caixa', () => {
        expect(filtrarConversas(conversas, 'salao')).toEqual([conversas[0]]);
        expect(filtrarConversas(conversas, 'CERTIFICADOS')).toEqual([
            conversas[1],
        ]);
    });

    it('busca vazia devolve tudo', () => {
        expect(filtrarConversas(conversas, '')).toEqual(conversas);
        expect(filtrarConversas(conversas, '   ')).toEqual(conversas);
    });
});
