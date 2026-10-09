import { enviarParaArquivo, ErroHttp, type ArquivoBaixado } from './http';

export type StatusDaLinha = 'ocioso' | 'gerando' | 'pronto' | 'erro';

export type ArquivoGerado = {
    url: string;
    nome: string;
    tipo: string;
};

export type LinhaDeGeracao = {
    id: number;
    valores: Record<string, string>;
    status: StatusDaLinha;
    arquivo: ArquivoGerado | null;
    erro: string | null;
};

export function valoresVazios(
    campos: { nome: string }[],
): Record<string, string> {
    return Object.fromEntries(campos.map((campo) => [campo.nome, '']));
}

export function novaLinha(
    id: number,
    campos: { nome: string }[],
    valores?: Record<string, string>,
): LinhaDeGeracao {
    return {
        id,
        valores: valores ?? valoresVazios(campos),
        status: 'ocioso',
        arquivo: null,
        erro: null,
    };
}

function alterar(
    linhas: LinhaDeGeracao[],
    id: number,
    mudar: (linha: LinhaDeGeracao) => LinhaDeGeracao,
): LinhaDeGeracao[] {
    return linhas.map((linha) => (linha.id === id ? mudar(linha) : linha));
}

/** Editar uma linha já gerada (ou com erro) a deixa pronta para gerar de novo. */
export function editarCelula(
    linhas: LinhaDeGeracao[],
    id: number,
    nome: string,
    valor: string,
): LinhaDeGeracao[] {
    return alterar(linhas, id, (linha) => ({
        ...linha,
        valores: { ...linha.valores, [nome]: valor },
        ...(linha.status === 'pronto' || linha.status === 'erro'
            ? { status: 'ocioso' as const, arquivo: null, erro: null }
            : {}),
    }));
}

export function removerLinha(
    linhas: LinhaDeGeracao[],
    id: number,
): LinhaDeGeracao[] {
    return linhas.filter((linha) => linha.id !== id);
}

export function marcarGerando(
    linhas: LinhaDeGeracao[],
    id: number,
): LinhaDeGeracao[] {
    return alterar(linhas, id, (linha) => ({
        ...linha,
        status: 'gerando',
        erro: null,
    }));
}

export function marcarPronto(
    linhas: LinhaDeGeracao[],
    id: number,
    arquivo: ArquivoGerado,
): LinhaDeGeracao[] {
    return alterar(linhas, id, (linha) => ({
        ...linha,
        status: 'pronto',
        arquivo,
        erro: null,
    }));
}

export function marcarErro(
    linhas: LinhaDeGeracao[],
    id: number,
    mensagem: string,
): LinhaDeGeracao[] {
    return alterar(linhas, id, (linha) => ({
        ...linha,
        status: 'erro',
        arquivo: null,
        erro: mensagem,
    }));
}

/** Linhas novas a partir de uma planilha importada, numeradas a partir de `primeiroId`. */
export function substituirLinhas(
    importadas: Record<string, string>[],
    primeiroId: number,
): LinhaDeGeracao[] {
    return importadas.map((valores, indice) =>
        novaLinha(primeiroId + indice, [], valores),
    );
}

/** Há algo na tabela que a importação substituiria: um valor preenchido ou uma linha já gerada/com erro. */
export function tabelaTemDados(linhas: LinhaDeGeracao[]): boolean {
    return linhas.some(
        (linha) =>
            linha.status !== 'ocioso' ||
            Object.values(linha.valores).some((valor) => valor.trim() !== ''),
    );
}

export function urlsDasLinhas(linhas: LinhaDeGeracao[]): string[] {
    return linhas.flatMap((linha) =>
        linha.arquivo ? [linha.arquivo.url] : [],
    );
}

/** Teto para a espera do Retry-After: um servidor que mande 1 hora não deixa a tela parada por tanto tempo. */
export const ESPERA_MAXIMA_SEGUNDOS = 60;

export type OpcoesDeGeracao = {
    nomePadrao: string;
    tentativas?: number;
    signal?: AbortSignal;
    esperar?: (milissegundos: number, signal?: AbortSignal) => Promise<void>;
};

/** Erro que o `fetch` (ou a espera) lança quando o sinal é abortado. */
export function erroDeCancelamento(): DOMException {
    return new DOMException('A geração foi cancelada.', 'AbortError');
}

/** Verdadeiro quando o erro veio de um cancelamento (AbortController), e não de falha real. */
export function ehCancelamento(erro: unknown): boolean {
    return erro instanceof DOMException && erro.name === 'AbortError';
}

/** Espera `milissegundos`, mas rejeita na hora se o sinal for abortado (e limpa o temporizador). */
export function esperarComSinal(
    milissegundos: number,
    signal?: AbortSignal,
): Promise<void> {
    return new Promise<void>((resolver, rejeitar) => {
        if (signal?.aborted) {
            rejeitar(erroDeCancelamento());

            return;
        }

        const cancelar = () => {
            clearTimeout(temporizador);
            rejeitar(erroDeCancelamento());
        };
        const temporizador = setTimeout(() => {
            signal?.removeEventListener('abort', cancelar);
            resolver();
        }, milissegundos);

        signal?.addEventListener('abort', cancelar, { once: true });
    });
}

/**
 * Pede ao servidor o PNG de uma linha. Se o limite por minuto estourar (429), espera o tempo que o
 * servidor mandar (no máximo 60 s) e tenta de novo, até `tentativas` vezes (padrão 4). Com `signal`
 * abortado, a requisição ou a espera são canceladas com AbortError.
 */
export async function gerarCertificado(
    url: string,
    valores: Record<string, string>,
    opcoes: OpcoesDeGeracao,
): Promise<ArquivoBaixado> {
    const maximo = opcoes.tentativas ?? 4;
    const esperar = opcoes.esperar ?? esperarComSinal;

    for (let tentativa = 1; ; tentativa++) {
        try {
            return await enviarParaArquivo(
                url,
                { valores },
                opcoes.nomePadrao,
                opcoes.signal,
            );
        } catch (erro) {
            if (
                !(erro instanceof ErroHttp) ||
                erro.status !== 429 ||
                tentativa >= maximo
            ) {
                throw erro;
            }

            const segundos = Math.min(
                erro.esperarSegundos ?? 5,
                ESPERA_MAXIMA_SEGUNDOS,
            );

            await esperar(segundos * 1000, opcoes.signal);
        }
    }
}
