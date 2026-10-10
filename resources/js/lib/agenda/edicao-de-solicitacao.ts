import type {
    CartaoDeSolicitacao,
    EquipamentoDaSolicitacao,
    EstadoDoCartao,
    SugestoesDaSolicitacao,
} from '@/types/agendamentos';

/** Campos que a secretaria edita no cartão. É o corpo do PUT, sem mudança de forma. */
export type EdicaoDaSolicitacao = {
    espaco_id: string | null;
    data: string;
    inicio: string;
    fim: string;
    pessoas: number | null;
    equipamentos: EquipamentoDaSolicitacao[];
};

export function edicaoDoCartao(
    cartao: CartaoDeSolicitacao,
): EdicaoDaSolicitacao {
    return {
        espaco_id: cartao.espaco_id,
        data: cartao.data,
        inicio: cartao.inicio,
        fim: cartao.fim,
        pessoas: cartao.pessoas,
        equipamentos: cartao.equipamentos.map((equipamento) => ({
            ...equipamento,
        })),
    };
}

/** Aplica o espaço sugerido. As sugestões do servidor já trazem o espaço livre no horário. */
export function aplicarSugestaoDeEspaco(
    edicao: EdicaoDaSolicitacao,
    espacoId: string,
): EdicaoDaSolicitacao {
    return { ...edicao, espaco_id: espacoId };
}

/** Aplica o horário livre sugerido, mantendo a data e o espaço. */
export function aplicarSugestaoDeHorario(
    edicao: EdicaoDaSolicitacao,
    horario: NonNullable<SugestoesDaSolicitacao['horario']>,
): EdicaoDaSolicitacao {
    return { ...edicao, inicio: horario.inicio, fim: horario.fim };
}

/** Marca o equipamento com uma unidade, ou tira da lista quando já estava marcado. */
export function alternarEquipamento(
    equipamentos: EquipamentoDaSolicitacao[],
    id: string,
    nome: string,
): EquipamentoDaSolicitacao[] {
    const existe = equipamentos.some((item) => item.id === id);

    if (existe) {
        return equipamentos.filter((item) => item.id !== id);
    }

    return [...equipamentos, { id, nome, quantidade: 1 }];
}

/** Muda a quantidade pedida, limitada entre 1 e o total do equipamento. */
export function ajustarQuantidade(
    equipamentos: EquipamentoDaSolicitacao[],
    id: string,
    quantidade: number,
    maximo: number,
): EquipamentoDaSolicitacao[] {
    const limite = Math.max(1, Math.floor(maximo));
    const nova = Math.min(Math.max(1, Math.floor(quantidade)), limite);

    return equipamentos.map((item) =>
        item.id === id ? { ...item, quantidade: nova } : item,
    );
}

/** Equipamento em manutenção na data conta como indisponível (manutenção até a data inclusive). */
export function estaEmManutencao(
    manutencaoAte: string | null,
    data: string,
): boolean {
    return manutencaoAte !== null && manutencaoAte >= data;
}

/** Diz se a aprovação pode seguir e, quando não pode, o motivo que vai no cartão. */
export function situacaoDaAprovacao(estado: EstadoDoCartao): {
    pode: boolean;
    motivo: string | null;
} {
    if (estado === 'sem_conflito') {
        return { pode: true, motivo: null };
    }

    if (estado === 'escolha_um_espaco') {
        return {
            pode: false,
            motivo: 'Escolha um espaço para poder aprovar.',
        };
    }

    if (estado === 'conflito') {
        return {
            pode: false,
            motivo: 'Resolva o conflito antes de aprovar: troque o espaço ou o horário.',
        };
    }

    return { pode: false, motivo: null };
}

/** Corpo do PUT: só espaço, horário, pessoas e pares id/quantidade dos equipamentos. */
export function payloadDaEdicao(edicao: EdicaoDaSolicitacao) {
    return {
        espaco_id: edicao.espaco_id,
        data: edicao.data,
        inicio: edicao.inicio,
        fim: edicao.fim,
        pessoas: edicao.pessoas,
        equipamentos: edicao.equipamentos.map(({ id, quantidade }) => ({
            id,
            quantidade,
        })),
    };
}
