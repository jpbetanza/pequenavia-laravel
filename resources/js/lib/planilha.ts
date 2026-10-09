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

/**
 * Lê a primeira aba: a primeira linha traz os cabeçalhos e cada linha seguinte vira um registro.
 * Lê as células direto (e não `sheet_to_json`) para manter o formato de exibição de cada uma.
 */
export function lerPlanilha(
    conteudo: ArrayBuffer,
    campos: { nome: string }[],
): Record<string, string>[] {
    const livro = XLSX.read(conteudo, { type: 'array', cellDates: true });
    const aba = livro.Sheets[livro.SheetNames[0]];

    if (!aba || !aba['!ref']) {
        return [];
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

    const linhas: Record<string, string>[] = [];

    for (let linha = intervalo.s.r + 1; linha <= intervalo.e.r; linha++) {
        const valores: Record<string, string> = {};
        let temValor = false;

        cabecalhos.forEach((nome, indice) => {
            const texto = textoDaCelula(celula(linha, intervalo.s.c + indice));

            valores[nome] = texto;
            temValor ||= texto !== '';
        });

        if (temValor) {
            linhas.push(
                Object.fromEntries(
                    campos.map((campo) => [
                        campo.nome,
                        valores[campo.nome] ?? '',
                    ]),
                ),
            );
        }
    }

    return linhas;
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
