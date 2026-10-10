import { normalizarBusca } from '@/lib/busca';

type GrupoBuscavel = {
    nome: string;
    coordenacao: string | null;
    contato: string | null;
};

/** Filtra por nome, coordenação ou contato, ignorando acento e caixa. Termo vazio devolve tudo. */
export function filtrarGrupos<T extends GrupoBuscavel>(
    grupos: T[],
    termo: string,
): T[] {
    const buscado = normalizarBusca(termo);

    if (buscado === '') {
        return grupos;
    }

    return grupos.filter((grupo) =>
        [grupo.nome, grupo.coordenacao, grupo.contato].some(
            (campo) =>
                campo !== null && normalizarBusca(campo).includes(buscado),
        ),
    );
}
