import { minutosDoHorario, horarioDeMinutos } from '@/lib/agenda/grade';

/** Conflito de uma data, como `agendamentos.disponibilidade` e o store o devolvem. */
export type ConflitoDaData = { tipo: string; mensagem: string };

export type SugestaoDeEspaco = {
    id: string;
    nome: string;
    capacidade: number | null;
};

export type SugestaoDeHorario = { inicio: string; fim: string };

export type DataDaDisponibilidade = {
    data: string;
    conflitos: ConflitoDaData[];
    sugestoes: {
        espacos: SugestaoDeEspaco[];
        horario: SugestaoDeHorario | null;
    };
};

export type RespostaDaDisponibilidade = { datas: DataDaDisponibilidade[] };

export const TIPO_EQUIPAMENTO_INDISPONIVEL = 'equipamento_indisponivel';

/** Datas da série que a pessoa não pulou. */
export function datasConsideradas(datas: string[], pulas: string[]): string[] {
    return datas.filter((data) => !pulas.includes(data));
}

/** Datas consideradas que têm pelo menos um conflito. */
export function datasComConflito(
    resposta: DataDaDisponibilidade[],
    pulas: string[],
): DataDaDisponibilidade[] {
    return resposta.filter(
        (item) => !pulas.includes(item.data) && item.conflitos.length > 0,
    );
}

/** Espaços e horários que resolvem os conflitos, sem repetir (espaço pelo id, horário pelo texto). */
export function sugestoesDosConflitos(conflitantes: DataDaDisponibilidade[]): {
    espacos: SugestaoDeEspaco[];
    horarios: SugestaoDeHorario[];
} {
    const espacos = new Map<string, SugestaoDeEspaco>();
    const horarios = new Map<string, SugestaoDeHorario>();

    for (const item of conflitantes) {
        for (const espaco of item.sugestoes.espacos) {
            espacos.set(espaco.id, espaco);
        }

        if (item.sugestoes.horario) {
            const horario = item.sugestoes.horario;

            horarios.set(`${horario.inicio}-${horario.fim}`, horario);
        }
    }

    return { espacos: [...espacos.values()], horarios: [...horarios.values()] };
}

export type ResumoDoEquipamento = {
    tom: 'ok' | 'alerta';
    texto: string;
};

/**
 * Situação de um equipamento pedido nas datas consideradas. A mensagem do servidor traz
 * "livres X de Y" quando falta unidade e "em manutenção até dd/mm" quando está parado.
 */
export function resumoDoEquipamento(
    nome: string,
    total: number,
    consideradas: DataDaDisponibilidade[],
): ResumoDoEquipamento {
    const prefixo = `“${nome}”`;
    const conflitos = consideradas.flatMap((item) =>
        item.conflitos
            .filter(
                (conflito) =>
                    conflito.tipo === TIPO_EQUIPAMENTO_INDISPONIVEL &&
                    conflito.mensagem.startsWith(prefixo),
            )
            .map((conflito) => ({
                data: item.data,
                mensagem: conflito.mensagem,
            })),
    );
    const totalDatas = consideradas.length;

    if (conflitos.length === 0) {
        return {
            tom: 'ok',
            texto:
                totalDatas === 1
                    ? 'Disponível nesta data'
                    : `Disponível nas ${totalDatas} datas`,
        };
    }

    const manutencao = conflitos.find((conflito) =>
        conflito.mensagem.includes('em manutenção'),
    );

    if (manutencao) {
        const ate = /até (\d{2}\/\d{2})/.exec(manutencao.mensagem)?.[1];

        return {
            tom: 'alerta',
            texto: ate ? `Em manutenção até ${ate}` : 'Em manutenção',
        };
    }

    const livres = conflitos
        .map((conflito) => /livres (\d+) de (\d+)/.exec(conflito.mensagem))
        .filter((casamento): casamento is RegExpExecArray => casamento !== null)
        .map((casamento) => Number(casamento[1]));
    const datasComFalta = new Set(conflitos.map((conflito) => conflito.data))
        .size;
    const sufixo = `em ${datasComFalta} das ${totalDatas} datas`;

    if (livres.length === 0) {
        return { tom: 'alerta', texto: `Indisponível ${sufixo}` };
    }

    return {
        tom: 'alerta',
        texto: `${Math.min(...livres)} de ${total} livres, falta ${sufixo}`,
    };
}

/** Horário final sugerido ao trocar o início: uma hora depois, sem passar de 22:00. */
export function fimPadrao(inicio: string): string {
    const minutos = Math.min(minutosDoHorario(inicio) + 60, 22 * 60);

    return horarioDeMinutos(minutos);
}

/** Verdadeiro quando o horário final é depois do inicial (formato "HH:MM"). */
export function horariosEmOrdem(inicio: string, fim: string): boolean {
    return minutosDoHorario(fim) > minutosDoHorario(inicio);
}

/** Texto do alerta: "N das M datas têm conflito", ou a forma singular quando só há uma data. */
export function textoDoAlertaDeConflito(
    conflitantes: number,
    consideradas: number,
): string {
    if (consideradas === 1) {
        return 'A data tem conflito';
    }

    return `${conflitantes} das ${consideradas} datas têm conflito`;
}
