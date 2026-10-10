import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    editarCelula,
    ehCancelamento,
    esperarComSinal,
    gerarCertificado,
    linhaPreenchida,
    linhasParaGerarTodas,
    marcarErro,
    marcarGerando,
    marcarPronto,
    mudarStatus,
    novaLinha,
    progresso,
    removerLinha,
    substituirLinhas,
    tabelaTemDados,
    urlsDasLinhas,
    valoresVazios,
} from './geracao';

const campos = [{ nome: 'Nome' }, { nome: 'Data' }];
const arquivo = {
    url: 'blob:1',
    nome: 'a.png',
    tipo: 'image/png',
    blob: new Blob(['png'], { type: 'image/png' }),
};

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('linhas de geração', () => {
    it('cria valores vazios para cada campo', () => {
        expect(valoresVazios(campos)).toEqual({ Nome: '', Data: '' });
    });

    it('cria uma linha ociosa, sem arquivo nem erro', () => {
        expect(novaLinha(1, campos)).toEqual({
            id: 1,
            valores: { Nome: '', Data: '' },
            status: 'ocioso',
            arquivo: null,
            erro: null,
        });
    });

    it('edita só a célula pedida, sem tocar nas outras linhas', () => {
        const linhas = [novaLinha(1, campos), novaLinha(2, campos)];

        const novas = editarCelula(linhas, 1, 'Nome', 'Maria');

        expect(novas[0].valores.Nome).toBe('Maria');
        expect(novas[1]).toBe(linhas[1]);
    });

    it('volta ao estado ocioso, sem arquivo, quando se edita uma linha pronta ou com erro', () => {
        const pronta = marcarPronto([novaLinha(1, campos)], 1, arquivo);
        const comErro = marcarErro([novaLinha(2, campos)], 2, 'falhou');

        expect(editarCelula(pronta, 1, 'Nome', 'x')[0]).toMatchObject({
            status: 'ocioso',
            arquivo: null,
            erro: null,
        });
        expect(editarCelula(comErro, 2, 'Nome', 'x')[0]).toMatchObject({
            status: 'ocioso',
            erro: null,
        });
    });

    it('marca a linha como gerando, pronta ou com erro', () => {
        const linhas = [novaLinha(1, campos)];

        expect(marcarGerando(linhas, 1)[0]).toMatchObject({
            status: 'gerando',
            erro: null,
        });
        expect(marcarPronto(linhas, 1, arquivo)[0]).toMatchObject({
            status: 'pronto',
            arquivo,
        });
        expect(marcarErro(linhas, 1, 'falhou')[0]).toMatchObject({
            status: 'erro',
            erro: 'falhou',
        });
    });

    it('remove a linha pedida', () => {
        const linhas = [novaLinha(1, campos), novaLinha(2, campos)];

        expect(removerLinha(linhas, 1).map((l) => l.id)).toEqual([2]);
    });

    it('substitui as linhas pelas importadas, numerando a partir do id dado', () => {
        const importadas = [
            { Nome: 'Maria', Data: '01/01/2026' },
            { Nome: 'João', Data: '' },
        ];

        const linhas = substituirLinhas(importadas, 10);

        expect(linhas.map((l) => l.id)).toEqual([10, 11]);
        expect(linhas[0]).toMatchObject({
            valores: importadas[0],
            status: 'ocioso',
        });
    });

    it('considera que a tabela tem dados quando há valor preenchido ou linha gerada/com erro', () => {
        expect(tabelaTemDados([novaLinha(1, campos)])).toBe(false);
        expect(
            tabelaTemDados(
                editarCelula([novaLinha(1, campos)], 1, 'Nome', ' '),
            ),
        ).toBe(false);
        expect(
            tabelaTemDados(
                editarCelula([novaLinha(1, campos)], 1, 'Nome', 'x'),
            ),
        ).toBe(true);
        expect(tabelaTemDados(marcarErro([novaLinha(1, campos)], 1, 'x'))).toBe(
            true,
        );
    });

    it('troca só o status da linha, sem tocar no arquivo nem no erro', () => {
        const linhas = marcarErro([novaLinha(1, campos)], 1, 'falhou');

        expect(mudarStatus(linhas, 1, 'na-fila')[0]).toMatchObject({
            status: 'na-fila',
            erro: 'falhou',
        });
    });

    it('considera preenchida a linha com algum valor que não seja só espaço', () => {
        expect(linhaPreenchida(novaLinha(1, campos))).toBe(false);
        expect(
            linhaPreenchida(
                editarCelula([novaLinha(1, campos)], 1, 'Data', '2026')[0],
            ),
        ).toBe(true);
    });

    it('seleciona para "Gerar todos" só as linhas preenchidas, não prontas e fora de andamento', () => {
        const linhas = [
            editarCelula([novaLinha(1, campos)], 1, 'Nome', 'Maria')[0],
            novaLinha(2, campos),
            marcarPronto(
                [editarCelula([novaLinha(3, campos)], 3, 'Nome', 'Ana')[0]],
                3,
                arquivo,
            )[0],
            marcarGerando(
                [editarCelula([novaLinha(4, campos)], 4, 'Nome', 'João')[0]],
                4,
            )[0],
            marcarErro(
                [editarCelula([novaLinha(5, campos)], 5, 'Nome', 'Rita')[0]],
                5,
                'falhou',
            )[0],
        ];

        expect(linhasParaGerarTodas(linhas).map((l) => l.id)).toEqual([1, 5]);
    });

    it('calcula o progresso entre as linhas preenchidas', () => {
        const preenchida = (id: number) =>
            editarCelula([novaLinha(id, campos)], id, 'Nome', 'x')[0];
        const linhas = [
            marcarPronto([preenchida(1)], 1, arquivo)[0],
            preenchida(2),
            novaLinha(3, campos),
        ];

        expect(progresso(linhas)).toEqual({
            prontas: 1,
            preenchidas: 2,
            percentual: 50,
        });
        expect(progresso([novaLinha(1, campos)])).toEqual({
            prontas: 0,
            preenchidas: 0,
            percentual: 0,
        });
    });

    it('lista as URLs dos arquivos gerados, para revogá-las', () => {
        const linhas = [
            marcarPronto([novaLinha(1, campos)], 1, arquivo)[0],
            novaLinha(2, campos),
        ];

        expect(urlsDasLinhas(linhas)).toEqual(['blob:1']);
    });
});

describe('gerarCertificado', () => {
    function fingir(...respostas: Response[]) {
        const falso = vi.fn();
        respostas.forEach((resposta) => falso.mockResolvedValueOnce(resposta));
        vi.stubGlobal('fetch', falso);
        vi.stubGlobal('document', { cookie: '' });

        return falso;
    }

    const png = () =>
        new Response(new Blob(['png'], { type: 'image/png' }), {
            headers: {
                'Content-Type': 'image/png',
                'Content-Disposition': 'attachment; filename="a.png"',
            },
        });

    const limite = () =>
        new Response(JSON.stringify({ message: 'Too Many Attempts.' }), {
            status: 429,
            headers: { 'Retry-After': '7', 'Content-Type': 'application/json' },
        });

    it('envia os valores e devolve o arquivo', async () => {
        const falso = fingir(png());

        const resultado = await gerarCertificado(
            '/gerar',
            { Nome: 'Maria' },
            { nomePadrao: 'x.png' },
        );

        expect(JSON.parse(falso.mock.calls[0][1].body)).toEqual({
            valores: { Nome: 'Maria' },
        });
        expect(resultado.nome).toBe('a.png');
    });

    it('não tenta de novo quando o erro não é 429', async () => {
        const falso = fingir(
            new Response(JSON.stringify({ message: 'Imagem ruim.' }), {
                status: 422,
            }),
        );

        await expect(
            gerarCertificado('/gerar', {}, { nomePadrao: 'x.png' }),
        ).rejects.toMatchObject({ status: 422 });
        expect(falso).toHaveBeenCalledTimes(1);
    });

    it('espera o Retry-After e tenta de novo quando o servidor responde 429', async () => {
        const falso = fingir(limite(), png());
        const esperar = vi.fn().mockResolvedValue(undefined);

        const resultado = await gerarCertificado(
            '/gerar',
            {},
            { nomePadrao: 'x.png', esperar },
        );

        expect(esperar.mock.calls[0][0]).toBe(7000);
        expect(falso).toHaveBeenCalledTimes(2);
        expect(resultado.nome).toBe('a.png');
    });

    it('avisa quando começa e quando termina a espera do 429', async () => {
        fingir(limite(), png());
        const esperar = vi.fn().mockResolvedValue(undefined);
        const aoEsperar = vi.fn();
        const aoRetomar = vi.fn();

        await gerarCertificado(
            '/gerar',
            {},
            { nomePadrao: 'x.png', esperar, aoEsperar, aoRetomar },
        );

        expect(aoEsperar).toHaveBeenCalledWith(7);
        expect(aoRetomar).toHaveBeenCalledTimes(1);
        expect(aoEsperar.mock.invocationCallOrder[0]).toBeLessThan(
            esperar.mock.invocationCallOrder[0],
        );
        expect(esperar.mock.invocationCallOrder[0]).toBeLessThan(
            aoRetomar.mock.invocationCallOrder[0],
        );
    });

    it('desiste depois do número máximo de tentativas', async () => {
        const falso = fingir(limite(), limite(), limite());
        const esperar = vi.fn().mockResolvedValue(undefined);

        await expect(
            gerarCertificado(
                '/gerar',
                {},
                { nomePadrao: 'x.png', esperar, tentativas: 3 },
            ),
        ).rejects.toMatchObject({ status: 429 });
        expect(falso).toHaveBeenCalledTimes(3);
        expect(esperar).toHaveBeenCalledTimes(2);
    });
});

describe('cancelamento da geração', () => {
    function fingir(...respostas: Response[]) {
        const falso = vi.fn();
        respostas.forEach((resposta) => falso.mockResolvedValueOnce(resposta));
        vi.stubGlobal('fetch', falso);
        vi.stubGlobal('document', { cookie: '' });

        return falso;
    }

    const limite = (retryAfter = '7') =>
        new Response(JSON.stringify({ message: 'Too Many Attempts.' }), {
            status: 429,
            headers: {
                'Retry-After': retryAfter,
                'Content-Type': 'application/json',
            },
        });

    it('reconhece só o AbortError como cancelamento', () => {
        expect(ehCancelamento(new DOMException('x', 'AbortError'))).toBe(true);
        expect(ehCancelamento(new Error('x'))).toBe(false);
        expect(ehCancelamento(undefined)).toBe(false);
    });

    it('cancela a espera do Retry-After na hora, limpa o temporizador e não tenta de novo', async () => {
        vi.useFakeTimers();

        try {
            const controle = new AbortController();
            const promessa = esperarComSinal(7000, controle.signal);
            expect(vi.getTimerCount()).toBe(1);

            controle.abort();
            const erro = await promessa.catch((e: unknown) => e);

            expect(ehCancelamento(erro)).toBe(true);
            expect(vi.getTimerCount()).toBe(0);
        } finally {
            vi.useRealTimers();
        }
    });

    it('rejeita a espera logo se o sinal já estiver abortado', async () => {
        const controle = new AbortController();
        controle.abort();

        const erro = await esperarComSinal(7000, controle.signal).catch(
            (e: unknown) => e,
        );

        expect(ehCancelamento(erro)).toBe(true);
    });

    it('aborta durante a espera do 429 e não faz a segunda requisição', async () => {
        const falso = fingir(limite(), new Response('png'));
        const controle = new AbortController();

        const promessa = gerarCertificado(
            '/gerar',
            {},
            { nomePadrao: 'x.png', signal: controle.signal },
        );

        await new Promise((resolver) => setTimeout(resolver, 10));
        controle.abort();

        const erro = await promessa.catch((e: unknown) => e);

        expect(ehCancelamento(erro)).toBe(true);
        expect(falso).toHaveBeenCalledTimes(1);
        expect(falso.mock.calls[0][1].signal).toBe(controle.signal);
    });

    it('limita a espera do Retry-After a 60 segundos', async () => {
        fingir(limite('3600'), new Response(new Blob(['png'])));
        const esperar = vi.fn().mockResolvedValue(undefined);

        await gerarCertificado('/gerar', {}, { nomePadrao: 'x.png', esperar });

        expect(esperar.mock.calls[0][0]).toBe(60_000);
    });
});
