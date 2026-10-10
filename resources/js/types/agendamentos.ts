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

/** Estado de um equipamento agora, calculado no servidor no fuso da agenda. */
export type AgoraDoEquipamento = {
    situacao: 'manutencao' | 'em_uso' | 'livre';
    /** Unidades ocupadas por reservas que cobrem este horário. */
    em_uso: number;
    total: number;
    livres: number;
    /** Espaços das reservas que estão usando o equipamento agora. */
    espacos: string[];
    /** Dia e mês do fim da manutenção, em "dd/mm". Só existe em manutenção. */
    ate: string | null;
};

/** Pico de uso simultâneo de um equipamento em um dia da semana. */
export type DiaDeUsoDoEquipamento = {
    /** Data "YYYY-MM-DD". */
    data: string;
    pico: number;
    quantidade: number;
};

/** Equipamento na tabela da página. */
export type EquipamentoResumo = {
    id: string;
    nome: string;
    descricao: string | null;
    quantidade: number;
    local_guarda: string | null;
    /** Data "YYYY-MM-DD" ou null. */
    manutencao_ate: string | null;
    em_manutencao: boolean;
    agora: AgoraDoEquipamento;
    /** Sete células, de segunda a domingo da semana atual. */
    semana: DiaDeUsoDoEquipamento[];
};

/** `levar` aparece 15 min antes do início da reserva; `recolher`, no fim. */
export type AcaoDeEquipamento = 'levar' | 'recolher';

export type ItemDeMovimento = {
    equipamento_id: string;
    nome: string;
    quantidade: number;
    /** Entregue (para levar) ou recolhido (para recolher). */
    marcado: boolean;
};

/** Uma linha do painel "Hoje: separar e recolher". */
export type MovimentoDeHoje = {
    chave: string;
    acao: AcaoDeEquipamento;
    /** "HH:MM" */
    horario: string;
    titulo: string;
    espaco: string;
    reserva_id: string;
    itens: ItemDeMovimento[];
};

export type PainelDeHoje = {
    /** Ex.: "sábado, 10 de outubro". */
    rotulo: string;
    movimentos: MovimentoDeHoje[];
};

/** Semana exibida no calendário: segunda e domingo, "YYYY-MM-DD". */
export type SemanaDoCalendario = {
    inicio: string;
    fim: string;
};

/** Reserva do intervalo, como o calendário a recebe. */
export type ReservaDoCalendario = {
    id: string;
    titulo: string;
    data: string;
    /** "HH:MM" */
    inicio: string;
    /** "HH:MM" */
    fim: string;
    pessoas: number | null;
    observacoes: string | null;
    /** Preenchido quando a reserva faz parte de uma série recorrente. */
    serie_id: string | null;
    espaco_id: string;
    grupo_id: string | null;
    grupo_nome: string | null;
    equipamentos: EquipamentoDaReserva[];
};

export type EquipamentoDaReserva = {
    id: string;
    nome: string;
    quantidade: number;
    entregue_em: string | null;
    recolhido_em: string | null;
};

/** Período fechado ou bloqueado de um espaço, com datas "YYYY-MM-DD". */
export type BloqueioDoCalendario = {
    id: string;
    inicio: string;
    fim: string;
    motivo: string;
};

/** Espaço ativo do dono, com horários e os bloqueios que tocam o intervalo. */
export type EspacoDoCalendario = {
    id: string;
    nome: string;
    /** Espaço inativo só chega à tela quando tem reserva no intervalo exibido. */
    ativo: boolean;
    capacidade: number | null;
    cor: CorDoEspaco;
    recursos_fixos: string[];
    horarios: HorarioDoEspaco[];
    bloqueios: BloqueioDoCalendario[];
};

export type EquipamentoDoCalendario = {
    id: string;
    nome: string;
    quantidade: number;
    /** Data "YYYY-MM-DD" ou null. */
    manutencao_ate: string | null;
};

export type GrupoDoCalendario = {
    id: string;
    nome: string;
};

/** Pedido pendente de reserva que cai no intervalo (usado na visão dia). */
export type SolicitacaoDoCalendario = {
    id: string;
    titulo: string;
    nome: string;
    telefone: string;
    data: string;
    inicio: string;
    fim: string;
    pessoas: number | null;
    espaco_id: string | null;
};

export type PropsDoCalendario = {
    semana: SemanaDoCalendario;
    visao: 'semana' | 'dia';
    /** Dia selecionado, "YYYY-MM-DD". */
    data: string;
    reservas: ReservaDoCalendario[];
    espacos: EspacoDoCalendario[];
    equipamentos: EquipamentoDoCalendario[];
    grupos: GrupoDoCalendario[];
    solicitacoes: SolicitacaoDoCalendario[];
    /** ISO 8601 no fuso da agenda, por exemplo "2026-10-04T23:00:00-03:00". */
    agora: string;
};

/** Status de uma solicitação, como as abas da tela da secretaria o nomeiam. */
export type StatusDaSolicitacao = 'pendente' | 'aprovada' | 'recusada';

/** Estado de conflito calculado no servidor. Nulo quando a solicitação já foi respondida. */
export type EstadoDoCartao =
    | 'sem_conflito'
    | 'conflito'
    | 'escolha_um_espaco'
    | null;

export type ConflitoDaSolicitacao = {
    tipo: string;
    mensagem: string;
};

export type SugestoesDaSolicitacao = {
    espacos: { id: string; nome: string; capacidade: number | null }[];
    horario: { inicio: string; fim: string } | null;
};

export type EquipamentoDaSolicitacao = {
    id: string;
    nome: string;
    quantidade: number;
};

/** Cartão de uma solicitação, como a tela da secretaria e a resposta do PUT o trazem. */
export type CartaoDeSolicitacao = {
    id: string;
    nome: string;
    telefone: string;
    titulo: string;
    grupo_nome: string | null;
    /** Data "YYYY-MM-DD". */
    data: string;
    /** "HH:MM" */
    inicio: string;
    /** "HH:MM" */
    fim: string;
    pessoas: number | null;
    observacoes: string | null;
    status: StatusDaSolicitacao;
    motivo_recusa: string | null;
    espaco_id: string | null;
    /** ISO 8601 no fuso da agenda. */
    recebida_em: string | null;
    equipamentos: EquipamentoDaSolicitacao[];
    reserva_id: string | null;
    estado: EstadoDoCartao;
    conflitos: ConflitoDaSolicitacao[];
    sugestoes: SugestoesDaSolicitacao;
};

export type PropsDaSolicitacoes = {
    link_do_formulario: string;
    contagens: Record<StatusDaSolicitacao, number>;
    status: StatusDaSolicitacao;
    solicitacoes: CartaoDeSolicitacao[];
    espacos: { id: string; nome: string; capacidade: number | null }[];
    equipamentos: {
        id: string;
        nome: string;
        quantidade: number;
        /** Data "YYYY-MM-DD" ou null. */
        manutencao_ate: string | null;
    }[];
};

/** Dados da agenda que o formulário público e a página de envio recebem. */
export type AgendaPublica = {
    nome: string;
    slug: string;
};

export type PropsDoFormularioPublico = {
    agenda: AgendaPublica;
    /** Hoje no fuso da agenda, "YYYY-MM-DD". */
    hoje: string;
    grupos: { id: string; nome: string }[];
    espacos: {
        id: string;
        nome: string;
        capacidade: number | null;
        antecedencia_min_horas: number;
        duracao_max_min: number | null;
    }[];
    equipamentos: { id: string; nome: string }[];
};
