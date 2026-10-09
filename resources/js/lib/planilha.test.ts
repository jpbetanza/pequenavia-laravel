import * as XLSX from 'xlsx';
import { describe, expect, it } from 'vite-plus/test';
import {
    cabecalhosFaltando,
    criarPlanilhaModelo,
    LIMITE_DE_LINHAS,
    lerPlanilha,
    lerPlanilhaComAviso,
    nomeDaPlanilhaModelo,
} from './planilha';

const campos = [{ nome: 'Nome' }, { nome: 'Data' }];

function escrever(aba: XLSX.WorkSheet): ArrayBuffer {
    const livro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(livro, aba, 'Dados');

    return XLSX.write(livro, {
        type: 'array',
        bookType: 'xlsx',
        cellDates: true,
    }) as ArrayBuffer;
}

function planilha(linhas: unknown[][]): ArrayBuffer {
    return escrever(XLSX.utils.aoa_to_sheet(linhas, { cellDates: true }));
}

describe('lerPlanilha', () => {
    it('mapeia as colunas pelo nome dos campos, ignora colunas a mais e preenche as que faltam com texto vazio', () => {
        const conteudo = planilha([
            ['Nome', 'Outra'],
            ['Maria', 'x'],
            ['João', 'y'],
        ]);

        expect(lerPlanilha(conteudo, campos)).toEqual([
            { Nome: 'Maria', Data: '' },
            { Nome: 'João', Data: '' },
        ]);
    });

    it('devolve números como texto', () => {
        const conteudo = planilha([
            ['Nome', 'Data'],
            ['Maria', 2024],
        ]);

        expect(lerPlanilha(conteudo, campos)[0].Data).toBe('2024');
    });

    it('formata datas como dd/mm/aaaa', () => {
        const conteudo = planilha([
            ['Nome', 'Data'],
            ['Maria', new Date(2026, 9, 9, 12, 0, 0)],
        ]);

        expect(lerPlanilha(conteudo, campos)[0].Data).toBe('09/10/2026');
    });

    it('formata datas à meia-noite local como dd/mm/aaaa', () => {
        const conteudo = planilha([
            ['Nome', 'Data'],
            ['Maria', new Date(2026, 9, 9, 0, 0, 0)],
        ]);

        expect(lerPlanilha(conteudo, campos)[0].Data).toBe('09/10/2026');
    });

    it('mantém o formato de exibição da célula, como 0000 para 123 virar 0123', () => {
        const aba = XLSX.utils.aoa_to_sheet([
            ['Nome', 'Data'],
            ['Maria', 123],
        ]);
        aba.B2.z = '0000';

        expect(lerPlanilha(escrever(aba), campos)[0].Data).toBe('0123');
    });

    it('ignora linhas totalmente em branco no meio da planilha', () => {
        const conteudo = planilha([
            ['Nome', 'Data'],
            ['Maria', 'x'],
            [],
            ['', ''],
            ['João', 'y'],
        ]);

        expect(lerPlanilha(conteudo, campos)).toEqual([
            { Nome: 'Maria', Data: 'x' },
            { Nome: 'João', Data: 'y' },
        ]);
    });

    it('ignora linhas com valor só em colunas que não são campos do modelo', () => {
        const conteudo = planilha([
            ['Nome', 'Data', 'Outra'],
            ['', '', 'x'],
            ['Maria', 'y', 'z'],
        ]);

        expect(lerPlanilha(conteudo, campos)).toEqual([
            { Nome: 'Maria', Data: 'y' },
        ]);
    });

    it('ignora linhas com só espaços nas colunas dos campos', () => {
        const conteudo = planilha([
            ['Nome', 'Data'],
            ['   ', ' \t '],
            ['Maria', 'x'],
        ]);

        expect(lerPlanilha(conteudo, campos)).toEqual([
            { Nome: 'Maria', Data: 'x' },
        ]);
    });

    it('para em 500 linhas de dados e ignora o resto', () => {
        const linhas: unknown[][] = [['Nome', 'Data']];

        for (let i = 1; i <= LIMITE_DE_LINHAS + 10; i++) {
            linhas.push([`Pessoa ${i}`, 'x']);
        }

        const importadas = lerPlanilha(planilha(linhas), campos);

        expect(LIMITE_DE_LINHAS).toBe(500);
        expect(importadas).toHaveLength(LIMITE_DE_LINHAS);
        expect(importadas[499].Nome).toBe('Pessoa 500');
    });

    it('corta valores com mais de 500 caracteres', () => {
        const longo = 'a'.repeat(600);
        const conteudo = planilha([
            ['Nome', 'Data'],
            [longo, 'x'],
        ]);

        const [linha] = lerPlanilha(conteudo, campos);

        expect(linha.Nome).toHaveLength(500);
        expect(linha.Nome).toBe('a'.repeat(500));
    });

    it('devolve lista vazia quando há só o cabeçalho', () => {
        expect(lerPlanilha(planilha([['Nome', 'Data']]), campos)).toEqual([]);
    });
});

describe('lerPlanilhaComAviso', () => {
    function linhasComDados(quantidade: number): unknown[][] {
        const linhas: unknown[][] = [['Nome', 'Data']];

        for (let i = 1; i <= quantidade; i++) {
            linhas.push([`Pessoa ${i}`, 'x']);
        }

        return linhas;
    }

    it('não marca truncada quando a planilha tem exatamente o limite de linhas', () => {
        const leitura = lerPlanilhaComAviso(
            planilha(linhasComDados(LIMITE_DE_LINHAS)),
            campos,
        );

        expect(leitura.truncada).toBe(false);
        expect(leitura.linhas).toHaveLength(LIMITE_DE_LINHAS);
    });

    it('marca truncada quando há mais linhas que o limite e devolve só o limite', () => {
        const leitura = lerPlanilhaComAviso(
            planilha(linhasComDados(LIMITE_DE_LINHAS + 1)),
            campos,
        );

        expect(leitura.truncada).toBe(true);
        expect(leitura.linhas).toHaveLength(LIMITE_DE_LINHAS);
        expect(leitura.linhas[LIMITE_DE_LINHAS - 1].Nome).toBe('Pessoa 500');
    });

    it('não conta como excedente uma linha extra sem dados nos campos', () => {
        const linhas = linhasComDados(LIMITE_DE_LINHAS);
        linhas.push(['', '']);

        const leitura = lerPlanilhaComAviso(planilha(linhas), campos);

        expect(leitura.truncada).toBe(false);
        expect(leitura.linhas).toHaveLength(LIMITE_DE_LINHAS);
    });

    it('devolve lista vazia e não truncada quando não há dados', () => {
        expect(
            lerPlanilhaComAviso(planilha([['Nome', 'Data']]), campos),
        ).toEqual({ linhas: [], truncada: false });
    });
});

describe('criarPlanilhaModelo', () => {
    it('cria uma aba "Dados" com os nomes dos campos como cabeçalho', () => {
        const livro = XLSX.read(criarPlanilhaModelo(campos), { type: 'array' });

        expect(livro.SheetNames).toEqual(['Dados']);
        expect(
            XLSX.utils.sheet_to_json(livro.Sheets.Dados, { header: 1 }),
        ).toEqual([['Nome', 'Data']]);
    });
});

describe('cabecalhosFaltando', () => {
    it('lista os campos sem coluna, comparando o cabeçalho sem espaços nas pontas', () => {
        const conteudo = planilha([
            [' Nome ', 'Outra'],
            ['Maria', 'x'],
        ]);

        expect(cabecalhosFaltando(conteudo, campos)).toEqual(['Data']);
    });

    it('devolve lista vazia quando todas as colunas existem', () => {
        const conteudo = planilha([['Data', 'Nome']]);

        expect(cabecalhosFaltando(conteudo, campos)).toEqual([]);
    });

    it('lista todos os campos quando a planilha está vazia', () => {
        expect(cabecalhosFaltando(planilha([]), campos)).toEqual([
            'Nome',
            'Data',
        ]);
    });
});

describe('nomeDaPlanilhaModelo', () => {
    it('mantém letras, números, espaços, traços, sublinhados e pontos', () => {
        expect(nomeDaPlanilhaModelo('Batismo_2026 v1.2-final')).toBe(
            'modelo-Batismo_2026 v1.2-final.xlsx',
        );
        expect(nomeDaPlanilhaModelo('Certificado de Participação')).toBe(
            'modelo-Certificado de Participação.xlsx',
        );
    });

    it('troca os demais caracteres por traço e tira espaços nas pontas', () => {
        expect(nomeDaPlanilhaModelo('  a/b\\c:d?e  ')).toBe(
            'modelo-a-b-c-d-e.xlsx',
        );
    });

    it('usa um nome padrão quando nada sobra do título', () => {
        expect(nomeDaPlanilhaModelo('   ')).toBe('modelo.xlsx');
    });
});
