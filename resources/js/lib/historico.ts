/** Valores na ordem dos campos do modelo; os que não estão no modelo vão depois, em ordem alfabética. */
export function ordenarValores(
    valores: Record<string, string>,
    ordem: string[],
): [string, string][] {
    const entradas = Object.entries(valores);
    const doModelo = ordem.flatMap((nome) =>
        entradas.filter(([chave]) => chave === nome),
    );
    const demais = entradas
        .filter(([chave]) => !ordem.includes(chave))
        .sort(([a], [b]) => a.localeCompare(b, 'pt-BR'));

    return [...doModelo, ...demais];
}

export function resumoDosValores(
    entradas: [string, string][],
    limite = 3,
): string {
    return entradas
        .slice(0, limite)
        .map(([chave, valor]) => `${chave}: ${valor}`)
        .join(' · ');
}

export function formatarData(iso: string, fusoHorario?: string): string {
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: fusoHorario,
    });
}

/** Valores sem o primeiro (que aparece em destaque na linha): só o texto, separado por " · ". */
export function valoresResumidos(
    entradas: [string, string][],
    limite = 2,
): string {
    return entradas
        .slice(0, limite)
        .map(([, valor]) => valor)
        .join(' · ');
}

const DIA = 24 * 60 * 60 * 1000;

function inicioDoDia(data: Date): Date {
    return new Date(data.getFullYear(), data.getMonth(), data.getDate());
}

/** Segunda-feira da semana que contém a data (semana começa na segunda). */
function inicioDaSemana(data: Date): Date {
    const inicio = inicioDoDia(data);
    const diaDaSemana = (inicio.getDay() + 6) % 7;

    return new Date(
        inicio.getFullYear(),
        inicio.getMonth(),
        inicio.getDate() - diaDaSemana,
    );
}

/**
 * Nome do grupo de um dia: "Hoje", "Ontem", o dia da semana dentro da semana atual
 * ("Terça-feira") ou a data ("12 de setembro", com o ano quando não é este).
 */
export function tituloDoDia(iso: string, agora: Date): string {
    const data = new Date(iso);
    const hoje = inicioDoDia(agora);
    const diaDoRegistro = inicioDoDia(data);
    const diasAtras = Math.round(
        (hoje.getTime() - diaDoRegistro.getTime()) / DIA,
    );

    if (diasAtras === 0) {
        return 'Hoje';
    }

    if (diasAtras === 1) {
        return 'Ontem';
    }

    const inicioDaSemanaAtual = inicioDaSemana(agora);

    if (diaDoRegistro >= inicioDaSemanaAtual && diaDoRegistro < hoje) {
        const diaDaSemana = data.toLocaleDateString('pt-BR', {
            weekday: 'long',
        });

        return diaDaSemana.charAt(0).toUpperCase() + diaDaSemana.slice(1);
    }

    return data.toLocaleDateString('pt-BR', {
        day: 'numeric',
        month: 'long',
        year:
            data.getFullYear() === agora.getFullYear() ? undefined : 'numeric',
    });
}

export function horaDaGeracao(iso: string): string {
    return new Date(iso).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export type GrupoDoHistorico<T> = {
    titulo: string;
    itens: T[];
};

/** Agrupa, mantendo a ordem recebida (mais recentes primeiro), pelo nome do dia. */
export function agruparPorDia<T extends { created_at: string }>(
    itens: T[],
    agora: Date,
): GrupoDoHistorico<T>[] {
    const grupos = new Map<string, T[]>();

    for (const item of itens) {
        const titulo = tituloDoDia(item.created_at, agora);
        grupos.set(titulo, [...(grupos.get(titulo) ?? []), item]);
    }

    return [...grupos].map(([titulo, itensDoGrupo]) => ({
        titulo,
        itens: itensDoGrupo,
    }));
}

/** Pares [texto, fundo] com tokens do tema; os tokens trocam sozinhos no modo escuro. */
const PARES_DO_SELO = [
    'text-primary bg-primary/10',
    'text-sucesso bg-sucesso-fundo',
    'text-aviso bg-aviso-fundo',
    'text-foreground bg-secondary',
];

/** Mesmo título, mesma cor: o hash do texto escolhe o par. */
export function corDoSelo(titulo: string): string {
    let hash = 0;

    for (const caractere of titulo) {
        hash = (hash * 31 + (caractere.codePointAt(0) ?? 0)) >>> 0;
    }

    return PARES_DO_SELO[hash % PARES_DO_SELO.length];
}
