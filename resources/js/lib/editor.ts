import type { Campo } from '@/types/certificados';

export const POSICAO_MAXIMA = 10000;

export function escalaDoPreview(
    larguraDoContainer: number,
    larguraNatural: number,
): number {
    return larguraNatural > 0 && larguraDoContainer > 0
        ? larguraDoContainer / larguraNatural
        : 1;
}

export function limitarPosicao(valor: number): number {
    return Math.min(POSICAO_MAXIMA, Math.max(0, Math.round(valor)));
}

/** Nova posição, em pixels da imagem original, depois de arrastar o campo na tela. */
export function moverCampo(
    inicio: { x: number; y: number },
    deslocamento: { dx: number; dy: number },
    escala: number,
    centralizado: boolean,
): { x?: number; y: number } {
    const y = limitarPosicao(inicio.y + deslocamento.dy / escala);

    return centralizado
        ? { y }
        : { x: limitarPosicao(inicio.x + deslocamento.dx / escala), y };
}

/** Atributos de um campo novo: herda o estilo do último campo e começa em 50, 50. */
export function valoresDoNovoCampo(
    ultimo: Campo | undefined,
): Pick<
    Campo,
    | 'cor'
    | 'tamanho_fonte'
    | 'fonte_familia'
    | 'peso_fonte'
    | 'espacamento_letras'
    | 'centralizado'
> & { x: number; y: number } {
    return {
        x: 50,
        y: 50,
        cor: ultimo?.cor ?? '#000000',
        tamanho_fonte: ultimo?.tamanho_fonte ?? 24,
        fonte_familia: ultimo?.fonte_familia ?? 'Georgia',
        peso_fonte: ultimo?.peso_fonte ?? 600,
        espacamento_letras: ultimo?.espacamento_letras ?? 0,
        centralizado: ultimo?.centralizado ?? false,
    };
}

/**
 * Volta para o valor confirmado pelo servidor só os campos da falha que ainda estão com o valor
 * otimista: se outra mudança já tiver sido feita depois, ela é mantida.
 */
export function reverterCampo(
    atual: Campo,
    mudancas: Partial<Campo>,
    confirmado: Campo,
): Campo {
    const reverso: Partial<Campo> = {};

    for (const chave of Object.keys(mudancas) as (keyof Campo)[]) {
        if (atual[chave] === mudancas[chave]) {
            Object.assign(reverso, { [chave]: confirmado[chave] });
        }
    }

    return { ...atual, ...reverso };
}
