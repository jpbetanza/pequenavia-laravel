import * as XLSX from 'xlsx';
import type { AnexoDaMensagem, TipoDeAnexo } from '@/types/assistente';

/** Mesmos limites de `config/assistente.php`. */
export const MAX_ANEXOS = 5;
export const MAX_BYTES_ARQUIVO = 5 * 1024 * 1024;
export const MAX_CARACTERES_ANEXO = 60_000;

/** Extensões aceitas no seletor de arquivos. */
export const EXTENSOES_ACEITAS =
    '.xlsx,.xls,.ods,.csv,.txt,.md,.png,.jpg,.jpeg,.webp,.gif,.pdf';

/** Resumo para a interface: o que aceita e os limites. */
export const DICA_DOS_ANEXOS =
    'Planilhas, PDF, imagens ou texto · até 5 arquivos de 5 MB';

const PLANILHAS = ['xlsx', 'xls', 'ods'];
const TEXTOS = ['txt', 'md', 'csv'];
const IMAGENS = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

/**
 * Anexo pronto para envio. Planilha e texto já vão convertidos em `conteudo`; imagem e PDF vão em `arquivo`.
 * `tamanho` é o tamanho do arquivo original, em bytes.
 */
export type AnexoPreparado = AnexoDaMensagem & {
    conteudo?: string;
    arquivo?: File;
};

function extensao(nome: string): string {
    const ponto = nome.lastIndexOf('.');

    return ponto === -1 ? '' : nome.slice(ponto + 1).toLowerCase();
}

/** Tipo do anexo pela extensão (o MIME do navegador varia por sistema). `null` se não for aceito. */
export function tipoDoArquivo(nome: string): TipoDeAnexo | null {
    const ext = extensao(nome);

    if (PLANILHAS.includes(ext) || ext === 'csv') {
        return 'planilha';
    }

    if (TEXTOS.includes(ext)) {
        return 'texto';
    }

    if (IMAGENS.includes(ext)) {
        return 'imagem';
    }

    return ext === 'pdf' ? 'pdf' : null;
}

/** Corta o texto no limite, na última quebra de linha antes dele, para não deixar uma linha pela metade. */
export function cortarTexto(
    texto: string,
    limite = MAX_CARACTERES_ANEXO,
): { texto: string; truncado: boolean } {
    if (texto.length <= limite) {
        return { texto, truncado: false };
    }

    const corte = texto.lastIndexOf('\n', limite);

    return {
        texto: texto.slice(0, corte > 0 ? corte : limite),
        truncado: true,
    };
}

/**
 * Texto de um arquivo: UTF-8; se não for UTF-8 válido, Windows-1252 (o CSV que o Excel salva no Brasil).
 */
export function decodificarTexto(conteudo: ArrayBuffer): string {
    try {
        return new TextDecoder('utf-8', { fatal: true })
            .decode(conteudo)
            .replace(/^﻿/, '');
    } catch {
        return new TextDecoder('windows-1252').decode(conteudo);
    }
}

/**
 * Planilha em texto para o assistente: cada aba com dados vira uma seção `## Aba: nome` seguida das linhas em CSV
 * (valores como aparecem na planilha; linhas vazias saem). O total é cortado em `limite` caracteres.
 */
export function planilhaEmTexto(
    conteudo: ArrayBuffer,
    limite = MAX_CARACTERES_ANEXO,
): { texto: string; truncado: boolean } {
    const livro = XLSX.read(conteudo, {
        type: 'array',
        cellDates: true,
        dateNF: 'dd/mm/yyyy',
    });

    const secoes = livro.SheetNames.flatMap((nome) => {
        const aba = livro.Sheets[nome];

        if (!aba || !aba['!ref']) {
            return [];
        }

        const csv = XLSX.utils
            .sheet_to_csv(aba, { blankrows: false, dateNF: 'dd/mm/yyyy' })
            .trim();

        return csv === '' ? [] : [`## Aba: ${nome}\n${csv}`];
    });

    return cortarTexto(secoes.join('\n\n'), limite);
}

export function formatarTamanho(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`;
}

/** Erro de um arquivo que não pode ser anexado. A mensagem vai direto para a secretaria. */
export class AnexoRecusado extends Error {}

/**
 * Lê e valida um arquivo escolhido ou solto no painel. Planilha e texto são convertidos aqui, no navegador.
 *
 * @throws AnexoRecusado
 */
export async function prepararAnexo(arquivo: File): Promise<AnexoPreparado> {
    const tipo = tipoDoArquivo(arquivo.name);

    if (tipo === null) {
        throw new AnexoRecusado(
            `${arquivo.name}: esse tipo de arquivo não é aceito. Use planilha, PDF, imagem ou texto.`,
        );
    }

    if (arquivo.size > MAX_BYTES_ARQUIVO) {
        throw new AnexoRecusado(
            `${arquivo.name}: cada arquivo pode ter até 5 MB.`,
        );
    }

    const base = {
        id: crypto.randomUUID(),
        nome: arquivo.name,
        tipo,
        tamanho: arquivo.size,
        truncado: false,
    };

    if (tipo === 'imagem' || tipo === 'pdf') {
        return { ...base, arquivo };
    }

    const bytes = await arquivo.arrayBuffer();
    let lido: { texto: string; truncado: boolean };

    try {
        lido =
            extensao(arquivo.name) === 'csv' || tipo === 'texto'
                ? cortarTexto(decodificarTexto(bytes))
                : planilhaEmTexto(bytes);
    } catch {
        throw new AnexoRecusado(
            `${arquivo.name}: não consegui ler o arquivo. Ele está corrompido ou protegido por senha?`,
        );
    }

    if (lido.texto.trim() === '') {
        throw new AnexoRecusado(`${arquivo.name}: o arquivo está vazio.`);
    }

    return { ...base, conteudo: lido.texto, truncado: lido.truncado };
}

/**
 * Junta os anexos novos aos que já estão no campo: ignora o mesmo arquivo de novo (nome e tamanho) e para no limite.
 * Devolve a lista nova e quantos ficaram de fora pelo limite.
 */
export function juntarAnexos(
    atuais: AnexoPreparado[],
    novos: AnexoPreparado[],
): { anexos: AnexoPreparado[]; recusadosPeloLimite: number } {
    const anexos = [...atuais];
    let recusadosPeloLimite = 0;

    for (const novo of novos) {
        const repetido = anexos.some(
            (anexo) =>
                anexo.nome === novo.nome && anexo.tamanho === novo.tamanho,
        );

        if (repetido) {
            continue;
        }

        if (anexos.length === MAX_ANEXOS) {
            recusadosPeloLimite++;
            continue;
        }

        anexos.push(novo);
    }

    return { anexos, recusadosPeloLimite };
}

/** O que a bolha da mensagem mostra de cada anexo (sem o conteúdo). */
export function resumoDoAnexo(anexo: AnexoPreparado): AnexoDaMensagem {
    return {
        id: anexo.id,
        nome: anexo.nome,
        tipo: anexo.tipo,
        tamanho: anexo.tamanho,
        truncado: anexo.truncado,
    };
}
