import type { CorDoEspaco } from '@/lib/agenda/cores';
import type { HorarioDoEspaco } from '@/lib/agenda/horarios';

/** Grupo ou pastoral da agenda, como a lista da página o recebe do servidor. */
export type GrupoResumo = {
    id: string;
    nome: string;
    coordenacao: string | null;
    contato: string | null;
    observacao: string | null;
    ativo: boolean;
    /** Até duas reuniões fixas, no formato "Seg 19:00 · Sala de catequese". */
    reunioes: string[];
};

/** Estado do espaço hoje: inativo tem prioridade sobre bloqueado. */
export type SituacaoDoEspaco = 'ativo' | 'bloqueado' | 'inativo';

/** Período em que o espaço fica indisponível, com datas "YYYY-MM-DD". */
export type BloqueioDoEspaco = {
    inicio: string;
    fim: string;
    motivo: string;
};

/** Espaço na listagem de cartões. */
export type EspacoResumo = {
    id: string;
    nome: string;
    capacidade: number | null;
    localizacao: string | null;
    cor: CorDoEspaco;
    recursos_fixos: string[];
    ativo: boolean;
    situacao: SituacaoDoEspaco;
    /** Bloqueio que cobre hoje, com a data final já em "dd/mm". Só existe quando o espaço está ativo. */
    bloqueio_atual: { motivo: string; ate: string } | null;
    horarios: HorarioDoEspaco[];
    reservas_na_semana: number;
};

/** Espaço completo, para o formulário de edição. */
export type EspacoFormulario = {
    id: string;
    nome: string;
    capacidade: number | null;
    localizacao: string | null;
    cor: CorDoEspaco;
    recursos_fixos: string[];
    aceita_solicitacoes: boolean;
    antecedencia_min_horas: number;
    duracao_max_min: number | null;
    intervalo_min: number;
    ativo: boolean;
    horarios: HorarioDoEspaco[];
    bloqueios: BloqueioDoEspaco[];
};
