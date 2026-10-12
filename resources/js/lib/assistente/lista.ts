import { normalizarBusca } from '@/lib/busca';
import type { ResumoDeConversa } from '@/types/assistente';

export type GrupoDeConversas = {
    rotulo: 'Hoje' | 'Esta semana' | 'Mais antigas';
    conversas: ResumoDeConversa[];
};

const FUSO = 'America/Sao_Paulo';

/** Dia do calendário no fuso de São Paulo, como número de dias desde 1970 (comparável entre datas). */
function diaNoFuso(iso: string): number {
    const partes = new Intl.DateTimeFormat('en-CA', {
        timeZone: FUSO,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date(iso));

    const valor = (tipo: string) =>
        Number(partes.find((parte) => parte.type === tipo)?.value);

    return (
        Date.UTC(valor('year'), valor('month') - 1, valor('day')) / 86_400_000
    );
}

/** Agrupa conversas por data da última mensagem. A semana começa na segunda-feira. */
export function agruparConversas(
    conversas: ResumoDeConversa[],
    agoraIso: string,
): GrupoDeConversas[] {
    const hoje = diaNoFuso(agoraIso);
    const diaDaSemana = new Date(hoje * 86_400_000).getUTCDay();
    const inicioDaSemana = hoje - ((diaDaSemana + 6) % 7);

    const grupos: Record<GrupoDeConversas['rotulo'], ResumoDeConversa[]> = {
        Hoje: [],
        'Esta semana': [],
        'Mais antigas': [],
    };

    for (const conversa of conversas) {
        if (conversa.ultima_mensagem_em === null) {
            grupos['Mais antigas'].push(conversa);
            continue;
        }

        const dia = diaNoFuso(conversa.ultima_mensagem_em);

        if (dia >= hoje) {
            grupos.Hoje.push(conversa);
        } else if (dia >= inicioDaSemana) {
            grupos['Esta semana'].push(conversa);
        } else {
            grupos['Mais antigas'].push(conversa);
        }
    }

    return (['Hoje', 'Esta semana', 'Mais antigas'] as const)
        .filter((rotulo) => grupos[rotulo].length > 0)
        .map((rotulo) => ({ rotulo, conversas: grupos[rotulo] }));
}

/** Filtra conversas pelo título ignorando acento e caixa. Busca vazia devolve tudo. */
export function filtrarConversas(
    conversas: ResumoDeConversa[],
    busca: string,
): ResumoDeConversa[] {
    const buscado = normalizarBusca(busca);

    if (buscado === '') {
        return [...conversas];
    }

    return conversas.filter((conversa) =>
        normalizarBusca(conversa.titulo).includes(buscado),
    );
}
