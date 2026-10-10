import type {
    BloqueioDoCalendario,
    EspacoDoCalendario,
    ReservaDoCalendario,
    SolicitacaoDoCalendario,
} from '@/types/agendamentos';
import { diaDaSemana } from '@/lib/agenda/datas';
import { horarioDeMinutos, minutosDoHorario } from '@/lib/agenda/grade';

/**
 * Estado de conflito de uma solicitação, calculado na hora pelo cliente.
 * É a mesma lógica simples da visão do dia: espaço fechado, bloqueado ou sobreposição com reserva do espaço.
 * O intervalo mínimo entre reservas não entra aqui; a aprovação passa pelo servidor, que confere tudo.
 */
export type EstadoDaSolicitacao =
    | { situacao: 'sem_espaco'; motivos: [] }
    | { situacao: 'sem_conflito'; motivos: [] }
    | { situacao: 'conflito'; motivos: string[] };

type SolicitacaoSemConflito = Pick<
    SolicitacaoDoCalendario,
    'espaco_id' | 'data' | 'inicio' | 'fim'
>;

type ReservaSemConflito = Pick<
    ReservaDoCalendario,
    'espaco_id' | 'data' | 'inicio' | 'fim' | 'titulo'
>;

export function estadoDaSolicitacao(
    solicitacao: SolicitacaoSemConflito,
    espacos: EspacoDoCalendario[],
    reservas: ReservaSemConflito[],
): EstadoDaSolicitacao {
    if (solicitacao.espaco_id === null) {
        return { situacao: 'sem_espaco', motivos: [] };
    }

    const espaco = espacos.find(
        (candidato) => candidato.id === solicitacao.espaco_id,
    );

    if (espaco === undefined || !espaco.ativo) {
        return {
            situacao: 'conflito',
            motivos: ['O espaço escolhido não está ativo.'],
        };
    }

    const motivos = [
        ...motivosDoBloqueio(espaco.bloqueios, solicitacao.data),
        ...motivosDoHorario(espaco, solicitacao),
        ...motivosDeReservas(espaco, solicitacao, reservas),
    ];

    return motivos.length === 0
        ? { situacao: 'sem_conflito', motivos: [] }
        : { situacao: 'conflito', motivos };
}

function motivosDoBloqueio(
    bloqueios: BloqueioDoCalendario[],
    data: string,
): string[] {
    const bloqueio = bloqueios.find(
        (candidato) => candidato.inicio <= data && data <= candidato.fim,
    );

    return bloqueio ? [`Bloqueado: ${bloqueio.motivo}.`] : [];
}

function motivosDoHorario(
    espaco: EspacoDoCalendario,
    solicitacao: SolicitacaoSemConflito,
): string[] {
    const horario = espaco.horarios.find(
        (candidato) => candidato.dia_semana === diaDaSemana(solicitacao.data),
    );

    if (horario === undefined) {
        return ['O espaço não abre neste dia da semana.'];
    }

    const inicio = minutosDoHorario(solicitacao.inicio);
    const fim = minutosDoHorario(solicitacao.fim);
    const abre = minutosDoHorario(horario.abre);
    const fecha = minutosDoHorario(horario.fecha);

    if (inicio < abre || fim > fecha) {
        return [
            `Fora do horário de funcionamento (abre às ${horarioDeMinutos(abre)}, fecha às ${horarioDeMinutos(fecha)}).`,
        ];
    }

    return [];
}

function motivosDeReservas(
    espaco: EspacoDoCalendario,
    solicitacao: SolicitacaoSemConflito,
    reservas: ReservaSemConflito[],
): string[] {
    const inicio = minutosDoHorario(solicitacao.inicio);
    const fim = minutosDoHorario(solicitacao.fim);

    return reservas
        .filter(
            (reserva) =>
                reserva.espaco_id === espaco.id &&
                reserva.data === solicitacao.data &&
                inicio < minutosDoHorario(reserva.fim) &&
                minutosDoHorario(reserva.inicio) < fim,
        )
        .map(
            (reserva) =>
                `Já reservado para “${reserva.titulo}”, ${reserva.inicio} – ${reserva.fim}.`,
        );
}
