// Props das telas de gestão. Espelham o backend (app/Services/Gestao): mudou lá, muda aqui.
// Datas 'YYYY-MM-DD' são do dia no fuso da gestão; os instantes (ISO) vêm com fuso.

export type PeriodoDaGestao = 7 | 30 | 90;

/** Opção de filtro: `valor` é o enum do backend; `rotulo` é o texto em pt-BR. */
export type OpcaoDaGestao = { valor: string; rotulo: string };

export type Paginator<T> = {
    current_page: number;
    data: T[];
    first_page_url: string | null;
    from: number | null;
    last_page: number;
    last_page_url: string | null;
    links: { url: string | null; label: string; active: boolean }[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

export type ClienteLinha = {
    id: string;
    nome: string;
    email: string;
    configuracao: { feitos: number; total: number } | null;
    reservas: number;
    conversas: number;
    resolvidas_pct: number | null;
    ultimo_acesso: string | null;
};

export type ClassificacaoDaGestao = {
    ultima: {
        iniciada_em: string;
        conversas_lidas: number;
        resumidas: number;
    } | null;
    /** Horário da próxima rodada, 'HH:MM'. */
    proxima: string;
};

export type CustoDoPainel = {
    assistente: number | null;
    jev: number;
    haiku: number;
    total: number;
};

export type ResumoDoPainel = {
    paroquias_ativas: number;
    paroquias_total: number;
    paroquias_novas: number;
    conversas: number;
    conversas_anterior: number;
    resolvidas_pct: number | null;
    a_revisar: number;
    acoes_confirmadas: number;
    acoes_propostas: number;
    custo: CustoDoPainel;
};

export type DiaDoPainel = {
    data: string;
    resolvidas: number;
    nao_resolvidas: number;
};

export type CasoDoPainel = {
    caso: string;
    rotulo: string;
    total: number;
    pct: number | null;
};

export type NivelDoAlerta = 'alto' | 'medio' | 'baixo';

export type Alerta = {
    tipo: string;
    nivel: NivelDoAlerta;
    titulo: string;
    texto: string;
    /** Caminho interno (rota já resolvida no backend). */
    link: string;
};

// Visão geral (/gestao)
export type VisaoGeralProps = {
    periodo: PeriodoDaGestao;
    resumo: ResumoDoPainel;
    por_dia: DiaDoPainel[];
    casos: CasoDoPainel[];
    alertas: Alerta[];
    clientes: ClienteLinha[];
    classificacao: ClassificacaoDaGestao;
};

// Clientes (/gestao/clientes)
export type ClientesProps = {
    periodo: PeriodoDaGestao;
    busca: string | null;
    clientes: ClienteLinha[];
};

// Cliente (/gestao/clientes/{user})
export type ClienteDetalhe = {
    id: string;
    nome: string;
    email: string;
    desde: string | null;
    ultimo_acesso: string | null;
};

export type SituacaoDoCliente = {
    nivel: 'bem' | 'atencao' | 'parado';
    texto: string;
};

export type ConfiguracaoDoCliente = {
    feitos: number;
    total: number;
    passos: { titulo: string; feito: boolean; pulado: boolean }[];
};

export type ClienteProps = {
    periodo: PeriodoDaGestao;
    cliente: ClienteDetalhe;
    situacao: SituacaoDoCliente;
    configuracao: ConfiguracaoDoCliente | null;
    cadastros: { espacos: number; equipamentos: number; grupos: number };
    reservas: {
        total: number;
        pelo_assistente: number;
        pelo_formulario: number;
    };
    assistente: {
        conversas: number;
        resolvidas_pct: number | null;
        custo: number | null;
    };
    casos: {
        rotulo: string;
        total: number;
        resolvida: number;
        parcial: number;
        nao_resolvida: number;
    }[];
    trava: { tema: string; conversas: number } | null;
    linha_do_tempo: {
        data: string | null;
        texto: string;
        tom: 'bom' | 'ruim' | 'neutro';
    }[];
    conversas: {
        id: string;
        titulo: string;
        caso: string;
        caso_rotulo: string;
        resultado: string;
        resultado_rotulo: string;
        frustracao: number;
        quando: string | null;
    }[];
};

// Conversas e casos de uso (/gestao/conversas)
export type FiltrosDeConversas = {
    caso: string | null;
    resultado: string | null;
    cliente: string | null;
    frustrada: boolean;
    a_revisar: boolean;
};

export type OpcoesDeConversas = {
    casos: OpcaoDaGestao[];
    resultados: OpcaoDaGestao[];
    clientes: { id: string; nome: string }[];
    temas: { id: string; nome: string }[];
};

export type LinhaDaMatriz = {
    caso: string;
    rotulo: string;
    total: number;
    resolvida: number;
    parcial: number;
    nao_resolvida: number;
    abandonada: number;
    tendencia_pct: number | null;
};

export type ConversaLinha = {
    id: string;
    /** Título já mascarado no backend; null quando não há título. */
    titulo: string | null;
    cliente: string;
    mensagens: number;
    caso: string;
    caso_rotulo: string;
    resultado: string;
    resultado_rotulo: string;
    frustracao: number;
    a_revisar: boolean;
    quando: string | null;
};

export type ConfiancaDoCampo = {
    valor: string;
    rotulo: string;
    confianca: number | null;
};

export type TemaDaConversa = {
    id: string;
    nome: string;
    origem: 'jev' | 'haiku' | 'gestor';
    confianca: number | null;
};

export type MensagemDaConversa = {
    id: string;
    de: 'secretaria' | 'assistente';
    texto: string;
    quando: string | null;
};

export type ConversaSelecionada = {
    id: string;
    titulo: string | null;
    cliente: { id: string; nome: string };
    quando: string | null;
    caso: ConfiancaDoCampo | null;
    resultado: ConfiancaDoCampo | null;
    frustracao: { nota: number; confianca: number } | null;
    abandonada: boolean | null;
    confusa: boolean | null;
    erro_do_assistente: boolean | null;
    tema: TemaDaConversa | null;
    resumo: string | null;
    ferramentas: string[];
    acoes: { propostas: number; confirmadas: number };
    tokens: { entrada: number; saida: number };
    custo_assistente: number | null;
    corrigida: boolean;
    a_revisar: boolean;
    modelo_jev: string | null;
    mensagens: MensagemDaConversa[];
};

export type ConversasProps = {
    periodo: PeriodoDaGestao;
    filtros: FiltrosDeConversas;
    opcoes: OpcoesDeConversas;
    matriz: LinhaDaMatriz[];
    conversas: Paginator<ConversaLinha>;
    a_revisar_total: number;
    selecionada: ConversaSelecionada | null;
};

// Pedidos não atendidos / temas (/gestao/temas)
export type SituacaoDoTema =
    | 'novo'
    | 'acompanhando'
    | 'planejado'
    | 'descartado';

export type TemaLinha = {
    id: string;
    nome: string;
    descricao: string | null;
    situacao: SituacaoDoTema;
    situacao_rotulo: string;
    criado_por: 'haiku' | 'gestor';
    criado_em: string | null;
    conversas: number;
    paroquias: number;
    frustradas: number;
    semana: number;
    semana_anterior: number;
};

export type RodadaDaGestao = {
    iniciada_em: string;
    terminada_em: string | null;
    conversas_lidas: number;
    resumidas: number;
    temas_novos: number;
    falhas: number;
    tokens_jev: number;
    custo_jev: number;
    custo_haiku: number;
    erro: string | null;
};

export type TemaSelecionado = {
    tema: TemaLinha;
    exemplos: { resumo: string; paroquia: string; data: string | null }[];
};

export type TemasProps = {
    periodo: PeriodoDaGestao;
    rodada: RodadaDaGestao | null;
    temas: TemaLinha[];
    selecionado: TemaSelecionado | null;
};

/** Compartilhado pelo servidor só para gestores; null para os demais. */
export type GestaoCompartilhado = {
    temas_novos: number;
    classificacao: ClassificacaoDaGestao;
    /** Se as contas de gestão (as suas) entram nos números e listas do painel. */
    incluir_gestores: boolean;
};
