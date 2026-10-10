const SEGUNDO = 1000;
const MINUTO = 60 * SEGUNDO;
const HORA = 60 * MINUTO;
export const DIA = 24 * HORA;

const UNIDADES: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 365 * DIA],
    ['month', 30 * DIA],
    ['week', 7 * DIA],
    ['day', DIA],
    ['hour', HORA],
    ['minute', MINUTO],
];

const formatador = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'always' });

/**
 * Tempo relativo em pt-BR ("há 2 dias"), a partir de uma data ISO.
 * Retorna null quando não há data válida. Abaixo de um minuto, "agora mesmo".
 */
export function tempoRelativo(
    iso: string | null,
    agora: Date = new Date(),
): string | null {
    if (iso === null) {
        return null;
    }

    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return null;
    }

    const diferenca = data.getTime() - agora.getTime();
    const absoluta = Math.abs(diferenca);

    for (const [unidade, milissegundos] of UNIDADES) {
        if (absoluta >= milissegundos) {
            return formatador.format(
                Math.trunc(diferenca / milissegundos),
                unidade,
            );
        }
    }

    return 'agora mesmo';
}
