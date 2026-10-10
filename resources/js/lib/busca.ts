/** Minúsculas, sem acento e sem espaços nas pontas, para comparar textos. */
export function normalizarBusca(texto: string): string {
    return texto
        .normalize('NFD')
        .replace(/\p{Mn}/gu, '')
        .toLocaleLowerCase('pt-BR')
        .trim();
}

/** Filtra pelo título ignorando acento e caixa. Termo vazio devolve tudo. */
export function filtrarPorTitulo<T extends { title: string }>(
    itens: T[],
    termo: string,
): T[] {
    const buscado = normalizarBusca(termo);

    if (buscado === '') {
        return itens;
    }

    return itens.filter((item) =>
        normalizarBusca(item.title).includes(buscado),
    );
}
