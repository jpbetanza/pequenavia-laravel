/**
 * Datas do calendário como strings "YYYY-MM-DD". Toda a aritmética é feita em UTC sobre
 * timestamps, então nenhuma data muda de dia por causa do fuso do navegador.
 * Horários "HH:MM" vêm do servidor já no fuso da agenda.
 */

const MILISSEGUNDOS_POR_DIA = 24 * 60 * 60 * 1000;

const REGEX_DATA = /^(\d{4})-(\d{2})-(\d{2})$/;

const DIAS_CURTOS = ['dom.', 'seg.', 'ter.', 'qua.', 'qui.', 'sex.', 'sáb.'];

const DIAS_LONGOS = [
    'domingo',
    'segunda-feira',
    'terça-feira',
    'quarta-feira',
    'quinta-feira',
    'sexta-feira',
    'sábado',
];

const MESES_CURTOS = [
    'jan.',
    'fev.',
    'mar.',
    'abr.',
    'mai.',
    'jun.',
    'jul.',
    'ago.',
    'set.',
    'out.',
    'nov.',
    'dez.',
];

const MESES_LONGOS = [
    'janeiro',
    'fevereiro',
    'março',
    'abril',
    'maio',
    'junho',
    'julho',
    'agosto',
    'setembro',
    'outubro',
    'novembro',
    'dezembro',
];

type PartesDaData = { ano: number; mes: number; dia: number };

/** Confere formato e existência no calendário: "2026-02-30" é inválida. */
export function ehDataISO(valor: string): boolean {
    const partes = lerPartes(valor);

    if (partes === null) {
        return false;
    }

    return deTimestamp(paraTimestamp(partes)) === valor;
}

function lerPartes(valor: string): PartesDaData | null {
    const casamento = REGEX_DATA.exec(valor);

    if (casamento === null) {
        return null;
    }

    return {
        ano: Number(casamento[1]),
        mes: Number(casamento[2]),
        dia: Number(casamento[3]),
    };
}

function paraTimestamp({ ano, mes, dia }: PartesDaData): number {
    return Date.UTC(ano, mes - 1, dia);
}

function deTimestamp(timestamp: number): string {
    const data = new Date(timestamp);
    const ano = String(data.getUTCFullYear()).padStart(4, '0');
    const mes = String(data.getUTCMonth() + 1).padStart(2, '0');
    const dia = String(data.getUTCDate()).padStart(2, '0');

    return `${ano}-${mes}-${dia}`;
}

function partesDe(iso: string): PartesDaData {
    const partes = lerPartes(iso);

    if (partes === null) {
        throw new RangeError(`Data inválida: ${iso}`);
    }

    return partes;
}

/** Soma (ou subtrai, com número negativo) dias corridos. */
export function somarDias(iso: string, dias: number): string {
    return deTimestamp(
        paraTimestamp(partesDe(iso)) + dias * MILISSEGUNDOS_POR_DIA,
    );
}

/** Dias corridos de `de` até `ate`: positivo quando `ate` é depois. */
export function diferencaEmDias(de: string, ate: string): number {
    return Math.round(
        (paraTimestamp(partesDe(ate)) - paraTimestamp(partesDe(de))) /
            MILISSEGUNDOS_POR_DIA,
    );
}

/** Dia da semana no padrão do servidor: 0 = domingo ... 6 = sábado. */
export function diaDaSemana(iso: string): number {
    return new Date(paraTimestamp(partesDe(iso))).getUTCDay();
}

/** Segunda-feira da semana que contém a data. */
export function inicioDaSemana(iso: string): string {
    const deslocamento = (diaDaSemana(iso) + 6) % 7;

    return somarDias(iso, -deslocamento);
}

/** Sete datas da semana, de segunda a domingo. */
export function diasDaSemana(iso: string): string[] {
    const segunda = inicioDaSemana(iso);

    return Array.from({ length: 7 }, (_, indice) => somarDias(segunda, indice));
}

/** Domingo que fecha a semana que contém a data. */
export function fimDaSemana(iso: string): string {
    return somarDias(inicioDaSemana(iso), 6);
}

/** Negativo se `a` vem antes de `b`, zero se são iguais, positivo se depois. */
export function compararDatas(a: string, b: string): number {
    return diferencaEmDias(b, a);
}

/** Primeiro dia do mês da data, deslocado em `meses` (pode ser negativo). */
export function deslocarMes(iso: string, meses: number): string {
    const { ano, mes } = partesDe(iso);
    const total = ano * 12 + (mes - 1) + meses;
    const novoAno = Math.floor(total / 12);
    const novoMes = (total % 12) + 1;

    return `${String(novoAno).padStart(4, '0')}-${String(novoMes).padStart(2, '0')}-01`;
}

/** "seg., 5 de out." */
export function formatarDiaCurto(iso: string): string {
    const { dia, mes } = partesDe(iso);

    return `${DIAS_CURTOS[diaDaSemana(iso)]}, ${dia} de ${MESES_CURTOS[mes - 1]}`;
}

/** "sábado, 10 de outubro de 2026" */
export function dataPorExtenso(iso: string): string {
    const { ano, dia, mes } = partesDe(iso);

    return `${DIAS_LONGOS[diaDaSemana(iso)]}, ${dia} de ${MESES_LONGOS[mes - 1]} de ${ano}`;
}

/**
 * Intervalo por extenso. Mesmo mês: "5 a 11 de outubro de 2026".
 * Meses diferentes: "28 de setembro a 4 de outubro de 2026".
 * Anos diferentes: "28 de dezembro de 2026 a 3 de janeiro de 2027".
 */
export function intervaloPorExtenso(inicio: string, fim: string): string {
    const a = partesDe(inicio);
    const b = partesDe(fim);

    if (a.ano !== b.ano) {
        return `${a.dia} de ${MESES_LONGOS[a.mes - 1]} de ${a.ano} a ${b.dia} de ${MESES_LONGOS[b.mes - 1]} de ${b.ano}`;
    }

    if (a.mes !== b.mes) {
        return `${a.dia} de ${MESES_LONGOS[a.mes - 1]} a ${b.dia} de ${MESES_LONGOS[b.mes - 1]} de ${b.ano}`;
    }

    return `${a.dia} a ${b.dia} de ${MESES_LONGOS[b.mes - 1]} de ${b.ano}`;
}

/** "Outubro 2026", para o cabeçalho do mini calendário. */
export function rotuloDoMes(iso: string): string {
    const { ano, mes } = partesDe(iso);
    const nome = MESES_LONGOS[mes - 1];

    return `${nome.charAt(0).toUpperCase()}${nome.slice(1)} ${ano}`;
}

/**
 * Semanas que cobrem o mês da data, cada uma com sete datas de segunda a domingo.
 * Os dias de fora do mês entram para completar a semana.
 */
export function gradeDoMes(iso: string): string[][] {
    const primeiro = deslocarMes(iso, 0);
    const ultimo = somarDias(deslocarMes(iso, 1), -1);
    const semanas: string[][] = [];

    for (
        let inicio = inicioDaSemana(primeiro);
        compararDatas(inicio, ultimo) <= 0;
        inicio = somarDias(inicio, 7)
    ) {
        semanas.push(diasDaSemana(inicio));
    }

    return semanas;
}

/** Dia "YYYY-MM-DD" de um "agora" ISO, lido no fuso que vier na string (o da agenda). */
export function hojeDe(agora: string): string {
    return agora.slice(0, 10);
}

/** Hora "HH:MM" de um "agora" ISO, no fuso que vier na string. */
export function horaDe(agora: string): string {
    return agora.slice(11, 16);
}
