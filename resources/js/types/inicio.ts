import type { CorDoEspaco } from '@/lib/agenda/cores';
import type { CartaoDeSolicitacao, PainelDeHoje } from '@/types/agendamentos';
import type { Periodo } from '@/lib/inicio/resumo';

export type DiaDoInicio = {
    data: string;
    /** Ex.: "sábado, 17 de outubro". */
    rotulo: string;
    /** "HH:MM" no fuso da agenda. */
    agora: string;
    periodo: Periodo;
};

export type ReservaDoInicio = {
    id: string;
    titulo: string;
    /** "HH:MM" */
    inicio: string;
    /** "HH:MM" */
    fim: string;
    espaco: { nome: string; cor: CorDoEspaco };
    grupo_nome: string | null;
    equipamentos: { nome: string; quantidade: number }[];
    situacao: 'encerrada' | 'em_andamento' | 'proxima';
};

/** Cartão de pedido com o nome e a cor do espaço escolhido, como o início os recebe. */
export type CartaoDoInicio = CartaoDeSolicitacao & {
    espaco_nome: string | null;
    espaco_cor: CorDoEspaco | null;
};

export type PropsDoInicio = {
    hoje: DiaDoInicio;
    reservas: ReservaDoInicio[];
    solicitacoes: {
        total: number;
        primeiras: CartaoDoInicio[];
    };
    equipamentos_hoje: PainelDeHoje;
    certificados: {
        emitidos_no_mes: number;
        ultimo: { modelo: string; gerado_em: string } | null;
    };
};
