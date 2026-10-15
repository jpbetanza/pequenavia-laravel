// Formatação das telas de gestão. Funções puras: recebem a data de referência por parâmetro para serem testáveis.

const FUSO = 'America/Sao_Paulo';
const TRAÇO = '—';
const MENOS = '−';
const DIA_EM_MS = 24 * 60 * 60 * 1000;
const DIAS_COM_NOME = 30;

const moeda = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const percentual = new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 1,
});
const numero = new Intl.NumberFormat('pt-BR');
const diaDaSemana = new Intl.DateTimeFormat('pt-BR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    timeZone: FUSO,
});
const dataComHora = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: FUSO,
});
const diaNoFuso = new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone: FUSO,
});

/** Custo em dólar, no formato pt-BR (`US$ 0,18`). Abaixo de um centavo, `< US$ 0,01`. */
export function formatarCusto(valor: number | null): string {
    if (valor === null) {
        return TRAÇO;
    }

    if (valor > 0 && valor < 0.01) {
        return '< US$ 0,01';
    }

    return `US$ ${moeda.format(valor)}`;
}

/** Percentual de uma casa, sem zeros à direita (`81%`, `81,5%`). Nulo vira traço. */
export function formatarPct(valor: number | null): string {
    if (valor === null) {
        return TRAÇO;
    }

    return `${percentual.format(valor)}%`;
}

export function formatarNumero(valor: number): string {
    return numero.format(valor);
}

/** Dia e mês de uma data 'YYYY-MM-DD' (`15/10`). Sem fuso: a data já é do dia no fuso da gestão. */
export function formatarData(data: string | null): string {
    const partes = data?.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!partes) {
        return TRAÇO;
    }

    return `${partes[3]}/${partes[2]}`;
}

/** Instante ISO mostrado no fuso da gestão (`15/10, 14:02`). */
export function formatarInstante(iso: string | null): string {
    if (iso === null) {
        return TRAÇO;
    }

    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return TRAÇO;
    }

    return dataComHora.format(data);
}

/** Dia de hoje no fuso da gestão, como 'YYYY-MM-DD'. */
export function hojeNoFuso(agora: Date = new Date()): string {
    // en-CA sai como 'AAAA-MM-DD', o formato das datas que vêm do backend.
    return diaNoFuso.format(agora);
}

/** Cabeçalho do dia (`Quinta-feira, 15 de outubro`), no fuso da gestão. */
export function dataDeHoje(agora: Date = new Date()): string {
    const texto = diaDaSemana.format(agora);

    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/** Dias de `data` até `referencia` (os dois em 'YYYY-MM-DD'); NaN quando alguma não é data. */
export function diasEntre(data: string, referencia: string): number {
    return Math.round(
        (Date.parse(`${referencia}T00:00:00Z`) -
            Date.parse(`${data}T00:00:00Z`)) /
            DIA_EM_MS,
    );
}

/**
 * Quando foi um dia, em relação a `referencia` (os dois em 'YYYY-MM-DD'): `hoje`, `ontem`,
 * `há N dias` até um mês e, depois disso, a data curta.
 */
export function diaRelativo(data: string | null, referencia: string): string {
    if (data === null) {
        return TRAÇO;
    }

    const dias = diasEntre(data, referencia);

    if (Number.isNaN(dias)) {
        return TRAÇO;
    }

    if (dias === 0) {
        return 'hoje';
    }

    if (dias === 1) {
        return 'ontem';
    }

    if (dias > 1 && dias <= DIAS_COM_NOME) {
        return `há ${dias} dias`;
    }

    return formatarData(data);
}

export type Tendencia = {
    texto: string;
    sentido: 'sobe' | 'desce' | 'igual';
};

/**
 * Variação de `atual` sobre `anterior`: `+18%`, `−6%` ou `=`. Sem base de comparação (anterior zero),
 * mostra `novo` quando há algo agora, porque um percentual sobre zero não existe.
 */
export function tendencia(atual: number, anterior: number): Tendencia {
    if (anterior === 0) {
        return atual === 0
            ? { texto: '=', sentido: 'igual' }
            : { texto: 'novo', sentido: 'sobe' };
    }

    const pct = Math.round(((atual - anterior) / anterior) * 100);

    if (pct === 0) {
        return { texto: '=', sentido: 'igual' };
    }

    if (pct > 0) {
        return { texto: `+${pct}%`, sentido: 'sobe' };
    }

    return { texto: `${MENOS}${Math.abs(pct)}%`, sentido: 'desce' };
}

/** Nota de frustração (0 a 3) a partir da qual a conversa conta como frustrada (PainelDeGestao::FRUSTRACAO_ALTA). */
export const FRUSTRACAO_ALTA = 1.5;

/** Nota de 0 a 3 da frustração, em palavras. */
export function rotuloDaFrustracao(nota: number): string {
    if (nota < 0.75) {
        return 'Calma';
    }

    if (nota < FRUSTRACAO_ALTA) {
        return 'Neutra';
    }

    if (nota < 2.25) {
        return 'Frustrada, mas educada';
    }

    return 'Muito frustrada';
}
