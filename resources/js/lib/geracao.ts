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

export function urlsDasLinhas(linhas: LinhaDeGeracao[]): string[] {
    return linhas.flatMap((linha) =>
        linha.arquivo ? [linha.arquivo.url] : [],
    );
}

export type OpcoesDeGeracao = {
    nomePadrao: string;
    tentativas?: number;
    esperar?: (milissegundos: number) => Promise<void>;
};

/**
 * Pede ao servidor o PNG de uma linha. Se o limite por minuto estourar (429), espera o tempo que o
 * servidor mandar e tenta de novo, até `tentativas` vezes (padrão 4).
 */
export async function gerarCertificado(
    url: string,
    valores: Record<string, string>,
    opcoes: OpcoesDeGeracao,
): Promise<ArquivoBaixado> {
    const maximo = opcoes.tentativas ?? 4;
    const esperar =
        opcoes.esperar ??
        ((ms: number) =>
            new Promise<void>((resolver) => setTimeout(resolver, ms)));

    for (let tentativa = 1; ; tentativa++) {
        try {
            return await enviarParaArquivo(url, { valores }, opcoes.nomePadrao);
        } catch (erro) {
            if (
                !(erro instanceof ErroHttp) ||
                erro.status !== 429 ||
                tentativa >= maximo
            ) {
                throw erro;
            }

            await esperar((erro.esperarSegundos ?? 5) * 1000);
        }
    }
}
