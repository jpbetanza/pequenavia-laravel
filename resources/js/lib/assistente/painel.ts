/** Abaixo de 768 px o painel do assistente é um diálogo em tela cheia (mesmo limite do `md` do Tailwind). */
export const CONSULTA_CELULAR = '(max-width: 767px)';

/**
 * Se o painel reabre aberto ao carregar a página. No celular ele sempre começa fechado: a preferência de "aberto"
 * é só do desktop, onde o painel é uma coluna ao lado do conteúdo.
 */
export function abertoAoCarregar(
    valorSalvo: string | null,
    celular: boolean,
): boolean {
    return !celular && valorSalvo === '1';
}

/** No celular a escolha de abrir ou fechar não é gravada, para não apagar a preferência do desktop. */
export function deveGravarPreferencia(celular: boolean): boolean {
    return !celular;
}
