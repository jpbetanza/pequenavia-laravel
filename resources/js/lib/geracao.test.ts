import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    editarCelula,
    gerarCertificado,
    marcarErro,
    marcarGerando,
    marcarPronto,
    novaLinha,
    removerLinha,
    substituirLinhas,
    urlsDasLinhas,
    valoresVazios,
} from './geracao';

const campos = [{ nome: 'Nome' }, { nome: 'Data' }];
const arquivo = { url: 'blob:1', nome: 'a.png', tipo: 'image/png' };

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

        expect(esperar).toHaveBeenCalledWith(7000);
        expect(falso).toHaveBeenCalledTimes(2);
        expect(resultado.nome).toBe('a.png');
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
