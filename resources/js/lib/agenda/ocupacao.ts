import type {
    BloqueioDoCalendario,
    EspacoDoCalendario,
} from '@/types/agendamentos';
import { diaDaSemana } from '@/lib/agenda/datas';
import {
    HORA_FINAL,
    HORA_INICIAL,
    horarioDeMinutos,
    minutosDoHorario,
} from '@/lib/agenda/grade';

/** Intervalo em minutos desde 00:00, no fechamento [inicio, fim). */
export type Faixa = { inicio: number; fim: number };

export type FaixaFechada = Faixa & {
    tipo: 'fechado' | 'bloqueado';
    texto: string;
};

export const MINUTO_DA_VISAO_INICIAL = HORA_INICIAL * 60;
export const MINUTO_DA_VISAO_FINAL = HORA_FINAL * 60;

/** Livres de um intervalo de funcionamento, descontando as reservas. Intervalos menores que `minimo` são descartados. */
export function intervalosLivres(
    janela: Faixa,
    ocupados: Faixa[],
    minimo = 30,
): Faixa[] {
    const ordenados = [...ocupados]
        .filter(
            (faixa) => faixa.fim > janela.inicio && faixa.inicio < janela.fim,
        )
        .sort((a, b) => a.inicio - b.inicio);
    const livres: Faixa[] = [];
    let cursor = janela.inicio;

    for (const faixa of ordenados) {
        if (faixa.inicio > cursor) {
            livres.push({ inicio: cursor, fim: faixa.inicio });
        }

        cursor = Math.max(cursor, faixa.fim);
    }

    if (janela.fim > cursor) {
        livres.push({ inicio: cursor, fim: janela.fim });
    }

    return livres.filter((faixa) => faixa.fim - faixa.inicio >= minimo);
}

export type OcupacaoDoEspaco = {
    /** Trechos hachurados: fechado antes ou depois do horário, ou bloqueio do dia inteiro. */
    fechadas: FaixaFechada[];
    /** Trechos de funcionamento sem reserva. */
    livres: Faixa[];
};

/** Faixas fechadas e livres de um espaço em uma data, com as reservas (já filtradas por espaço e dia). */
export function ocupacaoDoEspaco(
    espaco: Pick<EspacoDoCalendario, 'horarios' | 'bloqueios'>,
    data: string,
    reservas: Faixa[],
): OcupacaoDoEspaco {
    const inicioDoDia = MINUTO_DA_VISAO_INICIAL;
    const fimDoDia = MINUTO_DA_VISAO_FINAL;
    const bloqueio = espaco.bloqueios.find(
        (candidato) => candidato.inicio <= data && data <= candidato.fim,
    );

    if (bloqueio) {
        return {
            fechadas: [faixaDoDia(bloqueio, inicioDoDia, fimDoDia)],
            livres: [],
        };
    }

    const horario = espaco.horarios.find(
        (candidato) => candidato.dia_semana === diaDaSemana(data),
    );

    if (horario === undefined) {
        return {
            fechadas: [
                {
                    inicio: inicioDoDia,
                    fim: fimDoDia,
                    tipo: 'fechado',
                    texto: 'Fechado neste dia da semana',
                },
            ],
            livres: [],
        };
    }

    const abre = Math.max(minutosDoHorario(horario.abre), inicioDoDia);
    const fecha = Math.min(minutosDoHorario(horario.fecha), fimDoDia);
    const fechadas: FaixaFechada[] = [];

    if (abre > inicioDoDia) {
        fechadas.push({
            inicio: inicioDoDia,
            fim: abre,
            tipo: 'fechado',
            texto: 'Fechado',
        });
    }

    if (fecha < fimDoDia) {
        fechadas.push({
            inicio: fecha,
            fim: fimDoDia,
            tipo: 'fechado',
            texto: 'Fechado',
        });
    }

    return {
        fechadas,
        livres: intervalosLivres({ inicio: abre, fim: fecha }, reservas),
    };
}

function faixaDoDia(
    bloqueio: BloqueioDoCalendario,
    inicio: number,
    fim: number,
): FaixaFechada {
    return {
        inicio,
        fim,
        tipo: 'bloqueado',
        texto: `Bloqueado: ${bloqueio.motivo}`,
    };
}

/** "10:00–12:00", a forma do rótulo "Livre … · reservar". */
export function intervaloDeMinutos(faixa: Faixa): string {
    return `${horarioDeMinutos(faixa.inicio)}–${horarioDeMinutos(faixa.fim)}`;
}

/** Horário que o painel recebe ao abrir por um livre: o início do trecho, em "HH:MM". */
export function horarioDoLivre(faixa: Faixa): string {
    return horarioDeMinutos(faixa.inicio);
}
