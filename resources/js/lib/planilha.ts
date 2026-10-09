import * as XLSX from 'xlsx';

/** Datas do Excel não têm fuso: o SheetJS as devolve com o relógio em UTC, então lemos em UTC. */
function dataBrasileira(data: Date): string {
    const dia = String(data.getUTCDate()).padStart(2, '0');
    const mes = String(data.getUTCMonth() + 1).padStart(2, '0');

    return `${dia}/${mes}/${data.getUTCFullYear()}`;
}

function textoDaCelula(celula: XLSX.CellObject | undefined): string {
    if (!celula) {
        return '';
    }

    if (celula.t === 'd' && celula.v instanceof Date) {
        return dataBrasileira(celula.v);
    }

    return celula.w ?? String(celula.v ?? '');
}

/** Máximo de linhas de dados importadas de uma vez. */
export const LIMITE_DE_LINHAS = 500;

/** Máximo de caracteres de cada valor importado (igual ao `maxLength` das células). */
export const TAMANHO_MAXIMO_DO_VALOR = 500;

/** Máximo do arquivo enviado para importação: 5 MB. */
export const TAMANHO_MAXIMO_DO_ARQUIVO = 5 * 1024 * 1024;

type PrimeiraAba = {
    intervalo: XLSX.Range;
    celula: (linha: number, coluna: number) => XLSX.CellObject | undefined;
    cabecalhos: string[];
};

/** Abre a primeira aba e lê os cabeçalhos (primeira linha, sem espaços nas pontas). `null` se vazia. */
function abrirPrimeiraAba(conteudo: ArrayBuffer): PrimeiraAba | null {
    const livro = XLSX.read(conteudo, { type: 'array', cellDates: true });
    const aba = livro.Sheets[livro.SheetNames[0]];

    if (!aba || !aba['!ref']) {
        return null;
    }

    const intervalo = XLSX.utils.decode_range(aba['!ref']);
    const celula = (linha: number, coluna: number) =>
        aba[XLSX.utils.encode_cell({ r: linha, c: coluna })] as
            | XLSX.CellObject
            | undefined;

    const cabecalhos: string[] = [];

    for (let coluna = intervalo.s.c; coluna <= intervalo.e.c; coluna++) {
        cabecalhos.push(textoDaCelula(celula(intervalo.s.r, coluna)).trim());
    }

    return { intervalo, celula, cabecalhos };
}

/** Nomes dos campos que não têm coluna correspondente no cabeçalho da planilha. */
export function cabecalhosFaltando(
    conteudo: ArrayBuffer,
    campos: { nome: string }[],
): string[] {
    const cabecalhos = new Set(abrirPrimeiraAba(conteudo)?.cabecalhos ?? []);

    return campos
        .map((campo) => campo.nome)
        .filter((nome) => !cabecalhos.has(nome));
}

/** Resultado da leitura: os registros e se havia mais linhas de dados do que o limite. */
export type LeituraDaPlanilha = {
    linhas: Record<string, string>[];
    truncada: boolean;
};

/**
 * Lê a primeira aba: a primeira linha traz os cabeçalhos e cada linha seguinte vira um registro.
 * Lê as células direto (e não `sheet_to_json`) para manter o formato de exibição de cada uma.
 * Devolve no máximo `LIMITE_DE_LINHAS` registros, cortando cada valor em `TAMANHO_MAXIMO_DO_VALOR`.
 * `truncada` indica que havia mais linhas com dados do que o limite.
 */
export function lerPlanilhaComAviso(
    conteudo: ArrayBuffer,
    campos: { nome: string }[],
): LeituraDaPlanilha {
    const leitura = abrirPrimeiraAba(conteudo);

    if (!leitura) {
        return { linhas: [], truncada: false };
    }

    const { intervalo, celula, cabecalhos } = leitura;
    const linhas: Record<string, string>[] = [];
    let truncada = false;

    for (let linha = intervalo.s.r + 1; linha <= intervalo.e.r; linha++) {
        const valores: Record<string, string> = {};

        cabecalhos.forEach((nome, indice) => {
            valores[nome] = textoDaCelula(
                celula(linha, intervalo.s.c + indice),
            );
        });

        const temValorNosCampos = campos.some(
            (campo) => (valores[campo.nome] ?? '').trim() !== '',
        );

        if (!temValorNosCampos) {
            continue;
        }

        if (linhas.length === LIMITE_DE_LINHAS) {
            truncada = true;
            break;
        }

        linhas.push(
            Object.fromEntries(
                campos.map((campo) => [
                    campo.nome,
                    (valores[campo.nome] ?? '').slice(
                        0,
                        TAMANHO_MAXIMO_DO_VALOR,
                    ),
                ]),
            ),
        );
    }

    return { linhas, truncada };
}

/** Atalho de `lerPlanilhaComAviso` para quem só precisa dos registros. */
export function lerPlanilha(
    conteudo: ArrayBuffer,
    campos: { nome: string }[],
): Record<string, string>[] {
    return lerPlanilhaComAviso(conteudo, campos).linhas;
}

/** Nome do arquivo da planilha modelo: troca o que não é letra, número, espaço, traço, sublinhado ou ponto por "-". */
export function nomeDaPlanilhaModelo(titulo: string): string {
    const seguro = titulo.replace(/[^\p{L}\p{N} _.-]/gu, '-').trim();

    return seguro ? `modelo-${seguro}.xlsx` : 'modelo.xlsx';
}

/** Planilha vazia com os nomes dos campos como cabeçalho, para o usuário preencher. */
export function criarPlanilhaModelo(campos: { nome: string }[]): ArrayBuffer {
    const cabecalhos = campos.map((campo) => campo.nome);
    const aba = XLSX.utils.aoa_to_sheet([cabecalhos]);
    aba['!cols'] = cabecalhos.map((nome) => ({
        wch: Math.max(nome.length + 2, 20),
    }));

    const livro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(livro, aba, 'Dados');

    return XLSX.write(livro, {
        type: 'array',
        bookType: 'xlsx',
    }) as ArrayBuffer;
}
