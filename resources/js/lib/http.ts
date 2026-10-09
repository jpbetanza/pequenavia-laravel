export class ErroHttp extends Error {
    readonly status: number;

    readonly erros: Record<string, string[]>;

    readonly esperarSegundos: number | null;

    constructor(
        status: number,
        mensagem: string,
        erros: Record<string, string[]> = {},
        esperarSegundos: number | null = null,
    ) {
        super(mensagem);
        this.name = 'ErroHttp';
        this.status = status;
        this.erros = erros;
        this.esperarSegundos = esperarSegundos;
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
    let dados: { message?: string; errors?: Record<string, string[]> } = {};

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
    );
}

/** Envia JSON ao servidor (com CSRF) e devolve o JSON da resposta; `null` para 204. */
export async function enviarJson<T>(
    metodo: 'POST' | 'PATCH' | 'DELETE',
    url: string,
    corpo?: unknown,
): Promise<T | null> {
    const resposta = await fetch(url, {
        method: metodo,
        credentials: 'same-origin',
        headers: cabecalhos(
            corpo === undefined ? {} : { 'Content-Type': 'application/json' },
        ),
        body: corpo === undefined ? undefined : JSON.stringify(corpo),
    });

    if (!resposta.ok) {
        throw await erroDaResposta(resposta);
    }

    return resposta.status === 204 ? null : ((await resposta.json()) as T);
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
