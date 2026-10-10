/** Dia de funcionamento de um espaço, como o servidor o envia (`abre` e `fecha` em "HH:MM" ou "HH:MM:SS"). */
export type HorarioDoEspaco = {
    dia_semana: number;
    abre: string;
    fecha: string;
};

/** Segunda a domingo, porque o domingo (0) aparece por último no texto. */
const ORDEM_DA_SEMANA = [1, 2, 3, 4, 5, 6, 0];

const NOMES_DOS_DIAS: Record<number, string> = {
    0: 'Dom',
    1: 'Seg',
    2: 'Ter',
    3: 'Qua',
    4: 'Qui',
    5: 'Sex',
    6: 'Sáb',
};

type GrupoDeDias = { dias: number[]; horario: string };

/**
 * Resume os horários em uma linha, juntando dias seguidos com o mesmo horário.
 * Ex.: "Seg a sex 08:00–22:00 · Sáb 08:00–18:00". Dia sem horário é fechado.
 */
export function resumirHorarios(horarios: HorarioDoEspaco[]): string {
    if (horarios.length === 0) {
        return 'Sem horário definido';
    }

    const porDia = new Map(
        horarios.map((horario) => [
            horario.dia_semana,
            `${horario.abre.slice(0, 5)}–${horario.fecha.slice(0, 5)}`,
        ]),
    );

    const grupos: GrupoDeDias[] = [];
    let anterior: number | null = null;

    for (const dia of ORDEM_DA_SEMANA) {
        const horario = porDia.get(dia);

        if (horario === undefined) {
            anterior = null;
            continue;
        }

        const grupoAtual = grupos.at(-1);

        if (
            anterior !== null &&
            grupoAtual !== undefined &&
            grupoAtual.horario === horario
        ) {
            grupoAtual.dias.push(dia);
        } else {
            grupos.push({ dias: [dia], horario });
        }

        anterior = dia;
    }

    if (grupos.length === 0) {
        return 'Sem horário definido';
    }

    if (grupos.length === 1 && grupos[0].dias.length === 7) {
        return `Todos os dias ${grupos[0].horario}`;
    }

    return grupos
        .map((grupo) => `${rotuloDosDias(grupo.dias)} ${grupo.horario}`)
        .join(' · ');
}

function rotuloDosDias(dias: number[]): string {
    const primeiro = NOMES_DOS_DIAS[dias[0]];

    if (dias.length === 1) {
        return primeiro;
    }

    const ultimo = NOMES_DOS_DIAS[dias[dias.length - 1]].toLowerCase();

    return dias.length === 2
        ? `${primeiro} e ${ultimo}`
        : `${primeiro} a ${ultimo}`;
}
