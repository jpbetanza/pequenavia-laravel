import * as XLSX from 'xlsx';

function dataBrasileira(data: Date): string {
    const dia = String(data.getDate()).padStart(2, '0');
    const mes = String(data.getMonth() + 1).padStart(2, '0');

    return `${dia}/${mes}/${data.getFullYear()}`;
}

function textoDaCelula(valor: unknown): string {
    if (valor instanceof Date) {
        return dataBrasileira(valor);
    }

    if (typeof valor === 'string') {
        return valor;
    }

    if (typeof valor === 'number' || typeof valor === 'boolean') {
        return String(valor);
    }

    return '';
}

/** Lê a primeira aba e devolve uma linha por registro, com uma chave por campo do modelo. */
export function lerPlanilha(
    conteudo: ArrayBuffer,
    campos: { nome: string }[],
): Record<string, string>[] {
    const livro = XLSX.read(conteudo, { type: 'array', cellDates: true });
    const aba = livro.Sheets[livro.SheetNames[0]];

    if (!aba) {
        return [];
    }

    const linhas = XLSX.utils.sheet_to_json<Record<string, unknown>>(aba, {
        raw: true,
        defval: '',
    });

    return linhas.map((linha) =>
        Object.fromEntries(
            campos.map((campo) => [
                campo.nome,
                textoDaCelula(linha[campo.nome]),
            ]),
        ),
    );
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
