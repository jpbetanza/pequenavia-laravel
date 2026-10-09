import * as XLSX from 'xlsx';
import { describe, expect, it } from 'vite-plus/test';
import { criarPlanilhaModelo, lerPlanilha } from './planilha';

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

    it('devolve lista vazia quando há só o cabeçalho', () => {
        expect(lerPlanilha(planilha([['Nome', 'Data']]), campos)).toEqual([]);
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
