import * as XLSX from 'xlsx';
import { describe, expect, it } from 'vite-plus/test';
import {
    AnexoRecusado,
    type AnexoPreparado,
    cortarTexto,
    decodificarTexto,
    formatarTamanho,
    juntarAnexos,
    MAX_ANEXOS,
    MAX_BYTES_ARQUIVO,
    planilhaEmTexto,
    prepararAnexo,
    tipoDoArquivo,
} from './anexos';
import { corpoDaMensagem } from './enviar';

function livro(abas: Record<string, unknown[][]>): ArrayBuffer {
    const novo = XLSX.utils.book_new();

    for (const [nome, linhas] of Object.entries(abas)) {
        XLSX.utils.book_append_sheet(
            novo,
            XLSX.utils.aoa_to_sheet(linhas),
            nome,
        );
    }

    return XLSX.write(novo, { type: 'array', bookType: 'xlsx' }) as ArrayBuffer;
}

function arquivo(conteudo: BlobPart, nome: string): File {
    return new File([conteudo], nome);
}

const preparado = (nome: string, tamanho = 10): AnexoPreparado => ({
    id: nome,
    nome,
    tipo: 'texto',
    tamanho,
    truncado: false,
    conteudo: 'x',
});

describe('tipoDoArquivo', () => {
    it('decide pela extensão, sem diferenciar maiúsculas', () => {
        expect(tipoDoArquivo('Salas.XLSX')).toBe('planilha');
        expect(tipoDoArquivo('equipamentos.ods')).toBe('planilha');
        expect(tipoDoArquivo('grupos.csv')).toBe('planilha');
        expect(tipoDoArquivo('leia-me.txt')).toBe('texto');
        expect(tipoDoArquivo('quadro.jpeg')).toBe('imagem');
        expect(tipoDoArquivo('oficio.pdf')).toBe('pdf');
        expect(tipoDoArquivo('instalador.exe')).toBeNull();
        expect(tipoDoArquivo('sem-extensao')).toBeNull();
    });
});

describe('cortarTexto', () => {
    it('corta na última quebra de linha antes do limite e avisa', () => {
        expect(cortarTexto('aaa\nbbb\nccc', 9)).toEqual({
            texto: 'aaa\nbbb',
            truncado: true,
        });
        expect(cortarTexto('curto', 10)).toEqual({
            texto: 'curto',
            truncado: false,
        });
    });
});

describe('decodificarTexto', () => {
    it('lê UTF-8 sem o BOM e cai para Windows-1252 quando não é UTF-8', () => {
        const utf8 = new TextEncoder().encode('﻿Salão,Capela');
        // "Salão" em Windows-1252: o "ã" é o byte 0xE3.
        const windows = new Uint8Array([0x53, 0x61, 0x6c, 0xe3, 0x6f]);

        expect(decodificarTexto(utf8.buffer as ArrayBuffer)).toBe(
            'Salão,Capela',
        );
        expect(decodificarTexto(windows.buffer)).toBe('Salão');
    });
});

describe('planilhaEmTexto', () => {
    it('vira uma seção CSV por aba com dados, sem linhas e abas vazias', () => {
        const conteudo = livro({
            Salas: [
                ['Nome', 'Capacidade'],
                ['Salão paroquial', 120],
                [],
                ['Sala 3', 20],
            ],
            Vazia: [],
            Equipamentos: [
                ['Nome', 'Quantidade'],
                ['Projetor', 2],
            ],
        });

        expect(planilhaEmTexto(conteudo)).toEqual({
            texto: '## Aba: Salas\nNome,Capacidade\nSalão paroquial,120\nSala 3,20\n\n## Aba: Equipamentos\nNome,Quantidade\nProjetor,2',
            truncado: false,
        });
    });

    it('corta a planilha grande no limite', () => {
        const linhas = Array.from({ length: 50 }, (_, i) => [`Sala ${i}`, i]);

        const { texto, truncado } = planilhaEmTexto(
            livro({ Salas: linhas }),
            100,
        );

        expect(truncado).toBe(true);
        expect(texto.length).toBeLessThanOrEqual(100);
        expect(texto.endsWith('\n')).toBe(false);
    });
});

describe('prepararAnexo', () => {
    it('converte a planilha em texto e guarda o tamanho do arquivo original', async () => {
        const conteudo = livro({ Salas: [['Nome'], ['Capela']] });

        const anexo = await prepararAnexo(arquivo(conteudo, 'salas.xlsx'));

        expect(anexo).toMatchObject({
            nome: 'salas.xlsx',
            tipo: 'planilha',
            tamanho: conteudo.byteLength,
            conteudo: '## Aba: Salas\nNome\nCapela',
            truncado: false,
        });
        expect(anexo.arquivo).toBeUndefined();
    });

    it('lê o CSV como texto, sem passar pelo leitor de planilhas', async () => {
        const anexo = await prepararAnexo(
            arquivo('Nome;Capacidade\nCapela;40', 'salas.csv'),
        );

        expect(anexo.tipo).toBe('planilha');
        expect(anexo.conteudo).toBe('Nome;Capacidade\nCapela;40');
    });

    it('mantém imagem e PDF como arquivo', async () => {
        const pdf = arquivo('%PDF-1.4', 'oficio.pdf');

        const anexo = await prepararAnexo(pdf);

        expect(anexo.tipo).toBe('pdf');
        expect(anexo.arquivo).toBe(pdf);
        expect(anexo.conteudo).toBeUndefined();
    });

    it('recusa tipo não aceito, arquivo grande e arquivo vazio', async () => {
        await expect(
            prepararAnexo(arquivo('x', 'virus.exe')),
        ).rejects.toBeInstanceOf(AnexoRecusado);
        await expect(
            prepararAnexo(
                arquivo(new Uint8Array(MAX_BYTES_ARQUIVO + 1), 'foto.png'),
            ),
        ).rejects.toThrow('foto.png: cada arquivo pode ter até 5 MB.');
        await expect(
            prepararAnexo(arquivo('  \n', 'nada.txt')),
        ).rejects.toThrow('nada.txt: o arquivo está vazio.');
    });
});

describe('juntarAnexos', () => {
    it('ignora o mesmo arquivo de novo e para no limite', () => {
        const atuais = [preparado('a.txt'), preparado('b.txt')];
        const novos = [
            preparado('a.txt'),
            ...Array.from({ length: MAX_ANEXOS }, (_, i) =>
                preparado(`n${i}.txt`),
            ),
        ];

        const { anexos, recusadosPeloLimite } = juntarAnexos(atuais, novos);

        expect(anexos.map((anexo) => anexo.nome)).toEqual([
            'a.txt',
            'b.txt',
            'n0.txt',
            'n1.txt',
            'n2.txt',
        ]);
        expect(recusadosPeloLimite).toBe(2);
    });
});

describe('formatarTamanho', () => {
    it('usa B, KB ou MB com vírgula decimal', () => {
        expect(formatarTamanho(512)).toBe('512 B');
        expect(formatarTamanho(2048)).toBe('2 KB');
        expect(formatarTamanho(1.5 * 1024 * 1024)).toBe('1,5 MB');
    });
});

describe('corpoDaMensagem', () => {
    it('manda o texto e cada anexo como campos indexados', () => {
        const pdf = arquivo('%PDF', 'oficio.pdf');
        const corpo = corpoDaMensagem('Importe', [
            {
                ...preparado('salas.xlsx'),
                tipo: 'planilha',
                truncado: true,
            },
            {
                id: 'p',
                nome: 'oficio.pdf',
                tipo: 'pdf',
                tamanho: 4,
                truncado: false,
                arquivo: pdf,
            },
        ]);

        expect(corpo.get('texto')).toBe('Importe');
        expect(corpo.get('anexos[0][nome]')).toBe('salas.xlsx');
        expect(corpo.get('anexos[0][tipo]')).toBe('planilha');
        expect(corpo.get('anexos[0][conteudo]')).toBe('x');
        expect(corpo.get('anexos[0][truncado]')).toBe('1');
        expect(corpo.get('anexos[1][tipo]')).toBe('pdf');
        expect((corpo.get('anexos[1][arquivo]') as File).name).toBe(
            'oficio.pdf',
        );
        expect(corpo.has('anexos[1][conteudo]')).toBe(false);
    });

    it('sem texto, só os anexos', () => {
        expect(corpoDaMensagem('', []).has('texto')).toBe(false);
    });
});
