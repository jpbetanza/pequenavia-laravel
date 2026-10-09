import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    enviarJson,
    enviarParaArquivo,
    ErroHttp,
    lerTokenXsrf,
    mensagemDoErro,
    nomeDoArquivoDoCabecalho,
} from './http';

afterEach(() => {
    vi.unstubAllGlobals();
});

function respostaJson(corpo: unknown, init: ResponseInit = {}): Response {
    return new Response(JSON.stringify(corpo), {
        headers: { 'Content-Type': 'application/json' },
        ...init,
    });
}

function fingirFetch(resposta: Response) {
    const falso = vi.fn().mockResolvedValue(resposta);
    vi.stubGlobal('fetch', falso);
    vi.stubGlobal('document', { cookie: 'a=1; XSRF-TOKEN=tok%3D; b=2' });

    return falso;
}

describe('lerTokenXsrf', () => {
    it('lê e decodifica o token do cookie', () => {
        expect(lerTokenXsrf('a=1; XSRF-TOKEN=abc%3D%3D; b=2')).toBe('abc==');
    });

    it('devolve o texto cru quando o cookie não decodifica, sem lançar', () => {
        expect(() => lerTokenXsrf('XSRF-TOKEN=%E0%A4%A')).not.toThrow();
        expect(lerTokenXsrf('XSRF-TOKEN=%E0%A4%A')).toBe('%E0%A4%A');
    });

    it('devolve texto vazio quando não há cookie do token', () => {
        expect(lerTokenXsrf('a=1; b=2')).toBe('');
    });
});

describe('nomeDoArquivoDoCabecalho', () => {
    it('prefere o nome em UTF-8', () => {
        const cabecalho =
            'attachment; filename="Batismo Joao.png"; filename*=utf-8\'\'Batismo%20Jo%C3%A3o.png';

        expect(nomeDoArquivoDoCabecalho(cabecalho, 'x.png')).toBe(
            'Batismo João.png',
        );
    });

    it('usa o nome entre aspas quando não há UTF-8', () => {
        expect(
            nomeDoArquivoDoCabecalho('attachment; filename="a b.png"', 'x.png'),
        ).toBe('a b.png');
    });

    it('usa o nome padrão quando não há cabeçalho', () => {
        expect(nomeDoArquivoDoCabecalho(null, 'padrao.png')).toBe('padrao.png');
    });
});

describe('enviarJson', () => {
    it('envia o corpo como JSON com o token CSRF e devolve o JSON da resposta', async () => {
        const fetchFalso = fingirFetch(
            respostaJson({ id: '1' }, { status: 201 }),
        );

        const resultado = await enviarJson<{ id: string }>('POST', '/campos', {
            x: 5,
        });

        const [url, opcoes] = fetchFalso.mock.calls[0];
        expect(url).toBe('/campos');
        expect(opcoes.method).toBe('POST');
        expect(opcoes.credentials).toBe('same-origin');
        expect(opcoes.headers).toMatchObject({
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': 'tok=',
        });
        expect(opcoes.body).toBe('{"x":5}');
        expect(resultado).toEqual({ id: '1' });
    });

    it('devolve null quando a resposta é 204', async () => {
        fingirFetch(new Response(null, { status: 204 }));

        expect(await enviarJson('DELETE', '/campos/1')).toBeNull();
    });

    it('lança ErroHttp com a mensagem e os erros de validação do servidor', async () => {
        fingirFetch(
            respostaJson(
                { message: 'Inválido.', errors: { x: ['Negativo.'] } },
                { status: 422 },
            ),
        );

        const erro = await enviarJson('PATCH', '/campos/1', { x: -1 }).catch(
            (e: unknown) => e,
        );

        expect(erro).toBeInstanceOf(ErroHttp);
        expect(erro).toMatchObject({
            status: 422,
            message: 'Inválido.',
            erros: { x: ['Negativo.'] },
        });
    });

    it('guarda o Retry-After de uma resposta 429', async () => {
        fingirFetch(
            respostaJson(
                { message: 'Too Many Attempts.' },
                { status: 429, headers: { 'Retry-After': '7' } },
            ),
        );

        const erro = await enviarJson('POST', '/x').catch((e: unknown) => e);

        expect(erro).toMatchObject({ status: 429, esperarSegundos: 7 });
    });

    it('trata Retry-After inválido ou não positivo como ausente', async () => {
        fingirFetch(
            respostaJson(
                { message: 'Too Many Attempts.' },
                { status: 429, headers: { 'Retry-After': 'abc' } },
            ),
        );

        const erro = await enviarJson('POST', '/x').catch((e: unknown) => e);

        expect(erro).toMatchObject({ status: 429, esperarSegundos: null });
    });

    it('trata Retry-After zero como ausente', async () => {
        fingirFetch(
            respostaJson(
                { message: 'Too Many Attempts.' },
                { status: 429, headers: { 'Retry-After': '0' } },
            ),
        );

        const erro = await enviarJson('POST', '/x').catch((e: unknown) => e);

        expect(erro).toMatchObject({ status: 429, esperarSegundos: null });
    });

    it('troca a mensagem em inglês do 429 pela mensagem padrão em português', async () => {
        fingirFetch(
            respostaJson(
                { message: 'Too Many Attempts.' },
                { status: 429, headers: { 'Retry-After': '7' } },
            ),
        );

        const erro = await enviarJson('POST', '/x').catch((e: unknown) => e);

        expect(erro).toMatchObject({
            status: 429,
            message: 'Muitas tentativas. Aguarde alguns segundos.',
        });
    });

    it('troca a mensagem em inglês do 403 pela mensagem padrão em português', async () => {
        fingirFetch(
            respostaJson(
                { message: 'This action is unauthorized.' },
                { status: 403 },
            ),
        );

        const erro = await enviarJson('POST', '/x').catch((e: unknown) => e);

        expect(erro).toMatchObject({
            status: 403,
            message: 'Você não tem permissão para isso.',
        });
    });

    it('troca a mensagem do 419 pela mensagem padrão em português', async () => {
        fingirFetch(
            respostaJson({ message: 'CSRF token mismatch.' }, { status: 419 }),
        );

        const erro = await enviarJson('POST', '/x').catch((e: unknown) => e);

        expect(erro).toMatchObject({
            status: 419,
            message: 'Sua sessão expirou. Recarregue a página.',
        });
    });

    it('usa uma mensagem padrão quando o erro não vem em JSON', async () => {
        fingirFetch(new Response('<html>erro</html>', { status: 500 }));

        const erro = await enviarJson('POST', '/x').catch((e: unknown) => e);

        expect(erro).toMatchObject({
            status: 500,
            message: 'Erro no servidor. Tente novamente.',
        });
    });
});

describe('enviarParaArquivo', () => {
    it('devolve o arquivo com o nome do cabeçalho', async () => {
        fingirFetch(
            new Response(new Blob(['png'], { type: 'image/png' }), {
                headers: {
                    'Content-Type': 'image/png',
                    'Content-Disposition':
                        'attachment; filename="a.png"; filename*=utf-8\'\'Jo%C3%A3o.png',
                },
            }),
        );

        const arquivo = await enviarParaArquivo(
            '/gerar',
            { valores: {} },
            'padrao.png',
        );

        expect(arquivo.nome).toBe('João.png');
        expect(arquivo.tipo).toBe('image/png');
        expect(arquivo.blob.size).toBe(3);
    });

    it('lança ErroHttp com a mensagem do servidor quando a geração falha', async () => {
        fingirFetch(
            respostaJson(
                { message: 'Não foi possível ler a imagem do modelo.' },
                { status: 422 },
            ),
        );

        const erro = await enviarParaArquivo(
            '/gerar',
            { valores: {} },
            'x.png',
        ).catch((e: unknown) => e);

        expect(erro).toMatchObject({
            status: 422,
            message: 'Não foi possível ler a imagem do modelo.',
        });
    });
});

describe('mensagemDoErro', () => {
    it('devolve a mensagem de um ErroHttp', () => {
        expect(mensagemDoErro(new ErroHttp(422, 'Inválido.'))).toBe(
            'Inválido.',
        );
    });

    it('trata falha de conexão', () => {
        expect(mensagemDoErro(new TypeError('Failed to fetch'))).toBe(
            'Não foi possível conectar ao servidor. Verifique sua conexão.',
        );
    });

    it('trata qualquer outro erro', () => {
        expect(mensagemDoErro('x')).toBe('Não foi possível concluir a ação.');
    });
});
