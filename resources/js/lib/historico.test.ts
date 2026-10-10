import { describe, expect, it } from 'vite-plus/test';
import {
    agruparPorDia,
    corDoSelo,
    escolherDestaque,
    formatarData,
    horaDaGeracao,
    ordenarValores,
    resumoDosValores,
    tituloDoDia,
    valoresResumidos,
} from './historico';

describe('ordenarValores', () => {
    it('põe primeiro os campos na ordem do modelo e depois os demais, em ordem alfabética', () => {
        const valores = { Zeta: '3', Data: 'b', Nome: 'a', Alfa: '4' };

        expect(ordenarValores(valores, ['Nome', 'Data'])).toEqual([
            ['Nome', 'a'],
            ['Data', 'b'],
            ['Alfa', '4'],
            ['Zeta', '3'],
        ]);
    });

    it('ordena alfabeticamente quando o modelo foi excluído', () => {
        expect(ordenarValores({ B: '2', A: '1' }, [])).toEqual([
            ['A', '1'],
            ['B', '2'],
        ]);
    });

    it('trata uma lista vazia, que é como o PHP serializa um array vazio', () => {
        expect(
            ordenarValores([] as unknown as Record<string, string>, []),
        ).toEqual([]);
    });
});

describe('resumoDosValores', () => {
    it('junta os 3 primeiros com " · "', () => {
        const entradas: [string, string][] = [
            ['A', '1'],
            ['B', '2'],
            ['C', '3'],
            ['D', '4'],
        ];

        expect(resumoDosValores(entradas)).toBe('A: 1 · B: 2 · C: 3');
    });
});

describe('formatarData', () => {
    it('formata em português, com dia, mês, ano, hora e minuto', () => {
        expect(formatarData('2026-10-09T15:04:00Z', 'UTC')).toBe(
            '09/10/2026, 15:04',
        );
    });
});

describe('escolherDestaque', () => {
    it('escolhe o primeiro campo cujo nome contém "nome", sem diferenciar acento nem maiúsculas', () => {
        const entradas: [string, string][] = [
            ['Título', 'CERTIFICADO DE BATISMO'],
            ['Nome completo', 'Maria Clara'],
            ['Data', '2024'],
        ];

        expect(escolherDestaque(entradas)).toEqual({
            destaque: ['Nome completo', 'Maria Clara'],
            demais: [
                ['Título', 'CERTIFICADO DE BATISMO'],
                ['Data', '2024'],
            ],
        });
        expect(escolherDestaque([['NÓME da mãe', 'Ana']]).destaque).toEqual([
            'NÓME da mãe',
            'Ana',
        ]);
    });

    it('pula o campo "nome" que está vazio e usa o próximo com nome preenchido', () => {
        const entradas: [string, string][] = [
            ['Nome', '   '],
            ['Paróquia', 'São José'],
            ['Nome do padrinho', 'Pedro'],
        ];

        expect(escolherDestaque(entradas).destaque).toEqual([
            'Nome do padrinho',
            'Pedro',
        ]);
    });

    it('sem campo de nome, usa o primeiro valor preenchido', () => {
        const entradas: [string, string][] = [
            ['Título', 'CERTIFICADO'],
            ['Data', ''],
            ['Paróquia', 'São José'],
        ];

        expect(escolherDestaque(entradas)).toEqual({
            destaque: ['Título', 'CERTIFICADO'],
            demais: [
                ['Data', ''],
                ['Paróquia', 'São José'],
            ],
        });
    });

    it('sem nenhum valor preenchido não há destaque, e todos os campos ficam como demais', () => {
        const entradas: [string, string][] = [
            ['Nome', ''],
            ['Data', ' '],
        ];

        expect(escolherDestaque(entradas)).toEqual({
            destaque: null,
            demais: entradas,
        });
    });

    it('trata uma lista vazia', () => {
        expect(escolherDestaque([])).toEqual({ destaque: null, demais: [] });
    });
});

describe('valoresResumidos', () => {
    it('mostra só os valores, sem o primeiro, separados por " · "', () => {
        const entradas: [string, string][] = [
            ['Nome', 'Pedro'],
            ['Data', '14/09/2026'],
            ['Padrinhos', 'Carlos'],
            ['Celebrante', 'Pe. Antônio'],
        ];

        expect(valoresResumidos(entradas.slice(1))).toBe('14/09/2026 · Carlos');
    });
});

describe('tituloDoDia', () => {
    // Sábado, 10 de outubro de 2026. A semana vai de segunda (5) a domingo (11).
    const agora = new Date(2026, 9, 10, 12, 0);
    const iso = (
        ano: number,
        mes: number,
        dia: number,
        hora = 10,
        minuto = 0,
    ) => new Date(ano, mes, dia, hora, minuto).toISOString();

    it('usa Hoje e Ontem para os dois dias mais recentes', () => {
        expect(tituloDoDia(iso(2026, 9, 10, 9), agora)).toBe('Hoje');
        expect(tituloDoDia(iso(2026, 9, 9, 23), agora)).toBe('Ontem');
    });

    it('usa o dia da semana quando o registro é desta semana', () => {
        expect(tituloDoDia(iso(2026, 9, 6), agora)).toBe('Terça-feira');
        expect(tituloDoDia(iso(2026, 9, 5), agora)).toBe('Segunda-feira');
    });

    it('usa dia e mês fora da semana atual, sem o ano quando é este', () => {
        expect(tituloDoDia(iso(2026, 8, 12), agora)).toBe('12 de setembro');
    });

    it('acrescenta o ano quando não é o ano atual', () => {
        expect(tituloDoDia(iso(2025, 8, 12), agora)).toBe(
            '12 de setembro de 2025',
        );
    });
});

describe('horaDaGeracao', () => {
    it('mostra hora e minuto com dois dígitos', () => {
        expect(horaDaGeracao(new Date(2026, 9, 10, 9, 5).toISOString())).toBe(
            '09:05',
        );
    });
});

describe('agruparPorDia', () => {
    const agora = new Date(2026, 9, 10, 12, 0);
    const item = (id: string, dia: number, hora: number) => ({
        id,
        created_at: new Date(2026, 9, dia, hora, 0).toISOString(),
    });

    it('agrupa pelo nome do dia, mantendo a ordem recebida', () => {
        const grupos = agruparPorDia(
            [
                item('a', 10, 10),
                item('b', 10, 9),
                item('c', 9, 20),
                item('d', 6, 8),
            ],
            agora,
        );

        expect(grupos.map((grupo) => grupo.titulo)).toEqual([
            'Hoje',
            'Ontem',
            'Terça-feira',
        ]);
        expect(grupos[0].itens.map((registro) => registro.id)).toEqual([
            'a',
            'b',
        ]);
    });

    it('devolve lista vazia sem registros', () => {
        expect(agruparPorDia([], agora)).toEqual([]);
    });
});

describe('corDoSelo', () => {
    it('dá a mesma cor para o mesmo título', () => {
        expect(corDoSelo('Batismo')).toBe(corDoSelo('Batismo'));
    });

    it('usa sempre um dos pares da paleta', () => {
        const titulos = [
            'Batismo',
            'Matrimônio',
            'Primeira Eucaristia',
            'Crisma',
            'Missa',
            'Outro',
        ];
        const cores = new Set(titulos.map(corDoSelo));

        for (const cor of cores) {
            expect(cor).toMatch(/^text-\S+ bg-\S+$/);
        }

        expect(cores.size).toBeGreaterThan(1);
    });
});
