/**
 * Geometria da grade semanal: 07:00 a 22:00, 48 px por hora. Função pura, sem DOM.
 */

export const HORA_INICIAL = 7;
export const HORA_FINAL = 22;
export const PIXELS_POR_HORA = 48;
export const ALTURA_DA_GRADE = (HORA_FINAL - HORA_INICIAL) * PIXELS_POR_HORA;

const MINUTO_INICIAL = HORA_INICIAL * 60;
const MINUTO_FINAL = HORA_FINAL * 60;
const PASSO_DO_CLIQUE_MIN = 30;
/** Último horário que um clique pode iniciar: 21:30, para a reserva caber antes das 22:00. */
const ULTIMO_INICIO_DO_CLIQUE = MINUTO_FINAL - PASSO_DO_CLIQUE_MIN;

export type PosicaoDoBloco = {
    /** Distância do topo da grade, em px. */
    topo: number;
    /** Altura em px. Zero quando o bloco fica totalmente fora do intervalo visível. */
    altura: number;
};

export type PosicaoNaColuna = {
    /** Índice da coluna, começando em 0. */
    coluna: number;
    /** Quantas colunas o grupo de sobreposição ocupa no dia. */
    totalColunas: number;
};

type BlocoComHorario = { id: string; inicio: string; fim: string };

/** "HH:MM" ou "HH:MM:SS" para minutos desde 00:00. */
export function minutosDoHorario(horario: string): number {
    const [horas, minutos] = horario.split(':');

    return Number(horas) * 60 + Number(minutos);
}

/** Minutos desde 00:00 para "HH:MM". */
export function horarioDeMinutos(minutos: number): string {
    const horas = Math.floor(minutos / 60);
    const resto = minutos % 60;

    return `${String(horas).padStart(2, '0')}:${String(resto).padStart(2, '0')}`;
}

function minutoParaPixel(minuto: number): number {
    return ((minuto - MINUTO_INICIAL) / 60) * PIXELS_POR_HORA;
}

/** Topo e altura de um bloco, recortados ao intervalo 07:00–22:00. */
export function posicaoDoBloco(inicio: string, fim: string): PosicaoDoBloco {
    const inicioVisivel = Math.min(
        Math.max(minutosDoHorario(inicio), MINUTO_INICIAL),
        MINUTO_FINAL,
    );
    const fimVisivel = Math.min(
        Math.max(minutosDoHorario(fim), MINUTO_INICIAL),
        MINUTO_FINAL,
    );

    return {
        topo: minutoParaPixel(inicioVisivel),
        altura: Math.max(
            0,
            minutoParaPixel(fimVisivel) - minutoParaPixel(inicioVisivel),
        ),
    };
}

/**
 * Posição de cada reserva de um mesmo dia quando há sobreposição.
 * Blocos que se encostam (10:00–12:00 e 12:00–13:00) não se sobrepõem.
 * Cada grupo de blocos que se cruzam divide a largura em tantas colunas quantas
 * forem necessárias, e um bloco reaproveita a primeira coluna que já terminou.
 */
export function colunasLadoALado<T extends BlocoComHorario>(
    blocos: T[],
): Map<string, PosicaoNaColuna> {
    const resultado = new Map<string, PosicaoNaColuna>();
    const ordenados = [...blocos].sort(
        (a, b) =>
            minutosDoHorario(a.inicio) - minutosDoHorario(b.inicio) ||
            minutosDoHorario(b.fim) - minutosDoHorario(a.fim),
    );

    let grupo: T[] = [];
    let fimDoGrupo = -1;

    const fecharGrupo = () => {
        const colunasDoGrupo = atribuirColunas(grupo);

        for (const [id, coluna] of colunasDoGrupo.posicoes) {
            resultado.set(id, {
                coluna,
                totalColunas: colunasDoGrupo.total,
            });
        }

        grupo = [];
    };

    for (const bloco of ordenados) {
        const inicio = minutosDoHorario(bloco.inicio);

        if (grupo.length > 0 && inicio >= fimDoGrupo) {
            fecharGrupo();
        }

        grupo.push(bloco);
        fimDoGrupo = Math.max(fimDoGrupo, minutosDoHorario(bloco.fim));
    }

    if (grupo.length > 0) {
        fecharGrupo();
    }

    return resultado;
}

function atribuirColunas<T extends BlocoComHorario>(
    grupo: T[],
): { posicoes: Map<string, number>; total: number } {
    const fimDaColuna: number[] = [];
    const posicoes = new Map<string, number>();

    for (const bloco of grupo) {
        const inicio = minutosDoHorario(bloco.inicio);
        const fim = minutosDoHorario(bloco.fim);
        let coluna = fimDaColuna.findIndex((fimAtual) => fimAtual <= inicio);

        if (coluna === -1) {
            coluna = fimDaColuna.length;
            fimDaColuna.push(fim);
        } else {
            fimDaColuna[coluna] = fim;
        }

        posicoes.set(bloco.id, coluna);
    }

    return { posicoes, total: fimDaColuna.length };
}

/** Topo da linha "agora", ou null quando o horário está fora da grade. */
export function posicaoDoAgora(horaAgora: string): number | null {
    const minuto = minutosDoHorario(horaAgora);

    if (minuto < MINUTO_INICIAL || minuto >= MINUTO_FINAL) {
        return null;
    }

    return minutoParaPixel(minuto);
}

/**
 * Horário "HH:MM" de um clique na grade, arredondado para 30 minutos e limitado a 07:00–21:30.
 * `y` é a distância do clique ao topo da grade, em px.
 */
export function horaDoClique(y: number): string {
    const minutosAbsolutos = MINUTO_INICIAL + (y / PIXELS_POR_HORA) * 60;
    const arredondado =
        Math.round(minutosAbsolutos / PASSO_DO_CLIQUE_MIN) *
        PASSO_DO_CLIQUE_MIN;
    const limitado = Math.min(
        Math.max(arredondado, MINUTO_INICIAL),
        ULTIMO_INICIO_DO_CLIQUE,
    );

    return horarioDeMinutos(limitado);
}
