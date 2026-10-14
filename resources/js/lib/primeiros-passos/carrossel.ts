export type Direcao = 'avancar' | 'voltar';

/**
 * Para onde o carrossel vai: o card de destino (0 a total - 1) e de que lado o conteúdo entra.
 * Fora dos limites ou no próprio card, null (nada muda).
 */
export function navegar(
    atual: number,
    alvo: number,
    total: number,
): { card: number; direcao: Direcao } | null {
    if (alvo < 0 || alvo >= total || alvo === atual) {
        return null;
    }

    return { card: alvo, direcao: alvo > atual ? 'avancar' : 'voltar' };
}

/** Deslize do dedo na horizontal: para a esquerda avança, para a direita volta. Menos de 48 px não conta. */
export function cardDoDeslize(
    atual: number,
    deslocamentoX: number,
    deslocamentoY: number,
): number {
    if (
        Math.abs(deslocamentoX) < 48 ||
        Math.abs(deslocamentoX) < Math.abs(deslocamentoY)
    ) {
        return atual;
    }

    return deslocamentoX < 0 ? atual + 1 : atual - 1;
}
