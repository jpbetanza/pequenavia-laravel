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
