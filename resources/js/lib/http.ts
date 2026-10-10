export class ErroHttp extends Error {
    readonly status: number;

    readonly erros: Record<string, string[]>;

    readonly esperarSegundos: number | null;

    /** Corpo JSON completo da resposta de erro (por exemplo, os conflitos de um 422). */
    readonly corpo: Record<string, unknown> | null;

    constructor(
        status: number,
        mensagem: string,
        erros: Record<string, string[]> = {},
        esperarSegundos: number | null = null,
        corpo: Record<string, unknown> | null = null,
    ) {
        super(mensagem);
        this.name = 'ErroHttp';
        this.status = status;
        this.erros = erros;
        this.esperarSegundos = esperarSegundos;
        this.corpo = corpo;
    }
}

export type ArquivoBaixado = {
    blob: Blob;
    nome: string;
    tipo: string;
};

/** Lê o token CSRF que o Laravel grava no cookie XSRF-TOKEN. */
export function lerTokenXsrf(cookies: string): string {
    const par = cookies
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    if (!par) {
        return '';
    }

    const bruto = par.slice('XSRF-TOKEN='.length);

    try {
        return decodeURIComponent(bruto);
    } catch {
        return bruto;
    }
}

/** Nome do arquivo do `Content-Disposition`: prefere `filename*` (UTF-8) e cai para `filename`. */
export function nomeDoArquivoDoCabecalho(
    cabecalho: string | null,
    nomePadrao: string,
): string {
    if (cabecalho === null) {
        return nomePadrao;
    }

    const utf8 = cabecalho.match(/filename\*=utf-8''([^;]+)/i);

    if (utf8) {
        return decodeURIComponent(utf8[1]);
    }

    const simples = cabecalho.match(/filename="?([^";]+)"?/i);

    return simples ? simples[1] : nomePadrao;
}

function mensagemPadrao(status: number): string {
    if (status === 419) {
        return 'Sua sessão expirou. Recarregue a página.';
    }

    if (status === 429) {
        return 'Muitas tentativas. Aguarde alguns segundos.';
    }

    if (status === 403) {
        return 'Você não tem permissão para isso.';
    }

    if (status === 404) {
        return 'Não encontrado.';
    }

    return status >= 500
        ? 'Erro no servidor. Tente novamente.'
        : 'Não foi possível concluir a ação.';
}

export function mensagemDoErro(erro: unknown): string {
    if (erro instanceof ErroHttp) {
        return erro.message;
    }

    if (erro instanceof TypeError) {
        return 'Não foi possível conectar ao servidor. Verifique sua conexão.';
    }

    return 'Não foi possível concluir a ação.';
}

function cabecalhos(
    extras: Record<string, string> = {},
): Record<string, string> {
    const cookies = typeof document === 'undefined' ? '' : document.cookie;

    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': lerTokenXsrf(cookies),
        ...extras,
    };
}

async function erroDaResposta(resposta: Response): Promise<ErroHttp> {
    let dados: { message?: string; errors?: Record<string, string[]> } & Record<
        string,
        unknown
    > = {};

    try {
        dados = await resposta.json();
    } catch {
        dados = {};
    }

    const espera = Number(resposta.headers.get('Retry-After'));
    const esperarSegundos =
        resposta.headers.get('Retry-After') !== null &&
        Number.isFinite(espera) &&
        espera > 0
            ? espera
            : null;

    // O servidor só fala em português nos erros de validação (422); o resto vem em inglês.
    const mensagem =
        resposta.status === 422 && dados.message
            ? dados.message
            : mensagemPadrao(resposta.status);

    return new ErroHttp(
        resposta.status,
        mensagem,
        dados.errors ?? {},
        esperarSegundos,
        dados,
    );
}

/**
 * Envia JSON ao servidor (com CSRF) e devolve o JSON da resposta. Devolve `null` para 204 e
 * quando o servidor redireciona (`to_route` responde 302, e o navegador segue até a página);
 * nesse caso não há JSON para ler. Aceita `signal` para cancelar a requisição.
 *
 * PUT e DELETE vão como POST com `_method` na query. Sem o cabeçalho do Inertia, o 302 do
 * servidor manteria o método original ao seguir o redirect, e a página de calendário não aceita PUT.
 */
export async function enviarJson<T>(
    metodo: 'POST' | 'PATCH' | 'DELETE' | 'PUT',
    url: string,
    corpo?: unknown,
    signal?: AbortSignal,
): Promise<T | null> {
    const sobrescrito =
        metodo === 'PUT' || metodo === 'DELETE'
            ? `${url}${url.includes('?') ? '&' : '?'}_method=${metodo}`
            : url;
    const resposta = await fetch(sobrescrito, {
        method: metodo === 'PUT' || metodo === 'DELETE' ? 'POST' : metodo,
        credentials: 'same-origin',
        headers: cabecalhos(
            corpo === undefined ? {} : { 'Content-Type': 'application/json' },
        ),
        body: corpo === undefined ? undefined : JSON.stringify(corpo),
        signal,
    });

    if (!resposta.ok) {
        throw await erroDaResposta(resposta);
    }

    if (resposta.status === 204 || resposta.redirected) {
        return null;
    }

    return (await resposta.json()) as T;
}

/** Envia JSON ao servidor e devolve a resposta como arquivo (o PNG do certificado). Aceita `signal` para cancelar. */
export async function enviarParaArquivo(
    url: string,
    corpo: unknown,
    nomePadrao: string,
    signal?: AbortSignal,
): Promise<ArquivoBaixado> {
    const resposta = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: cabecalhos({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(corpo),
        signal,
    });

    if (!resposta.ok) {
        throw await erroDaResposta(resposta);
    }

    return {
        blob: await resposta.blob(),
        nome: nomeDoArquivoDoCabecalho(
            resposta.headers.get('Content-Disposition'),
            nomePadrao,
        ),
        tipo:
            resposta.headers.get('Content-Type')?.split(';')[0].trim() ??
            'application/octet-stream',
    };
}
