import type {
    BloqueioDoCalendario,
    EspacoDoCalendario,
    ReservaDoCalendario,
} from '@/types/agendamentos';
import { diaDaSemana } from '@/lib/agenda/datas';
import { minutosDoHorario } from '@/lib/agenda/grade';

/** Reservas de um dia, na ordem de início. */
export function reservasDoDia(
    reservas: ReservaDoCalendario[],
    data: string,
): ReservaDoCalendario[] {
    return reservas
        .filter((reserva) => reserva.data === data)
        .sort(
            (a, b) => minutosDoHorario(a.inicio) - minutosDoHorario(b.inicio),
        );
}

/**
 * Posição do marcador "Agora" na lista do dia: quantas reservas já começaram.
 * Devolve null quando o dia não é hoje.
 */
export function posicaoDoAgoraNaLista(
    reservasOrdenadas: ReservaDoCalendario[],
    ehHoje: boolean,
    horaAgora: string,
): number | null {
    if (!ehHoje) {
        return null;
    }

    const minutoAgora = minutosDoHorario(horaAgora);

    return reservasOrdenadas.filter(
        (reserva) => minutosDoHorario(reserva.inicio) <= minutoAgora,
    ).length;
}

/** Espaços que estão livres neste momento: abertos, sem bloqueio e sem reserva cobrindo a hora. */
export function espacosLivresAgora(
    espacos: EspacoDoCalendario[],
    reservas: ReservaDoCalendario[],
    data: string,
    horaAgora: string,
): EspacoDoCalendario[] {
    const minuto = minutosDoHorario(horaAgora);

    return espacos.filter((espaco) => {
        if (!espaco.ativo) {
            return false;
        }

        const bloqueado = espaco.bloqueios.some(
            (bloqueio: BloqueioDoCalendario) =>
                bloqueio.inicio <= data && data <= bloqueio.fim,
        );
        const horario = espaco.horarios.find(
            (candidato) => candidato.dia_semana === diaDaSemana(data),
        );
        const aberto =
            horario !== undefined &&
            minuto >= minutosDoHorario(horario.abre) &&
            minuto < minutosDoHorario(horario.fecha);
        const ocupado = reservas.some(
            (reserva) =>
                reserva.espaco_id === espaco.id &&
                reserva.data === data &&
                minutosDoHorario(reserva.inicio) <= minuto &&
                minuto < minutosDoHorario(reserva.fim),
        );

        return aberto && !bloqueado && !ocupado;
    });
}
