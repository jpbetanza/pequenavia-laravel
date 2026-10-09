import { toast } from 'sonner';

const MENSAGEM_PADRAO = 'Não foi possível concluir a ação. Tente novamente.';

function mostrarErro(erros?: Record<string, unknown>): void {
    const primeiroValor = erros ? Object.values(erros)[0] : undefined;
    const primeiraMensagem = Array.isArray(primeiroValor)
        ? primeiroValor[0]
        : primeiroValor;

    toast.error(
        typeof primeiraMensagem === 'string' && primeiraMensagem !== ''
            ? primeiraMensagem
            : MENSAGEM_PADRAO,
    );
}

export const tratamentoDeErro = {
    onError: (erros: Record<string, unknown>) => mostrarErro(erros),
    onHttpException: () => {
        mostrarErro();

        return false;
    },
    onNetworkError: () => {
        mostrarErro();

        return false;
    },
};
