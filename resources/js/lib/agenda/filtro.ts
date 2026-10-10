/** Chave do localStorage com a seleção de espaços do calendário. */
export const CHAVE_DO_FILTRO = 'agenda.calendario.espacos';

/**
 * Guarda os espaços marcados e os que já existiam ao salvar. Um espaço criado depois
 * não estava em `conhecidos`, então aparece marcado por padrão.
 */
export type FiltroSalvo = {
    selecionados: string[];
    conhecidos: string[];
};

function ehListaDeTextos(valor: unknown): valor is string[] {
    return (
        Array.isArray(valor) && valor.every((item) => typeof item === 'string')
    );
}

/** Lê o valor bruto do storage. Qualquer forma inesperada vira null. */
export function lerFiltroSalvo(bruto: string | null): FiltroSalvo | null {
    if (bruto === null) {
        return null;
    }

    try {
        const valor: unknown = JSON.parse(bruto);

        if (
            typeof valor === 'object' &&
            valor !== null &&
            ehListaDeTextos((valor as FiltroSalvo).selecionados) &&
            ehListaDeTextos((valor as FiltroSalvo).conhecidos)
        ) {
            return valor as FiltroSalvo;
        }
    } catch {
        return null;
    }

    return null;
}

/** Espaços que devem aparecer marcados, na ordem em que vieram. Sem registro salvo, todos. */
export function reconciliarSelecao(
    salvo: FiltroSalvo | null,
    idsAtuais: string[],
): string[] {
    if (salvo === null) {
        return idsAtuais;
    }

    return idsAtuais.filter(
        (id) =>
            salvo.selecionados.includes(id) || !salvo.conhecidos.includes(id),
    );
}

export function montarFiltroSalvo(
    selecionados: string[],
    idsAtuais: string[],
): FiltroSalvo {
    return { selecionados, conhecidos: idsAtuais };
}
