export type ChaveDoPasso =
    | 'espacos'
    | 'equipamentos'
    | 'grupos'
    | 'formulario'
    | 'avisos';

export type PassoDoRoteiro = {
    chave: ChaveDoPasso;
    titulo: string;
    descricao: string;
    feito: boolean;
    pulado: boolean;
};

/** Roteiro de primeiros passos, como o servidor compartilha em todas as páginas. Null: não mostrar. */
export type PrimeirosPassos = {
    fase: 'inicio' | 'andamento' | 'concluido';
    /** Passos resolvidos (feitos ou pulados). */
    feitos: number;
    total: number;
    /** Primeiro passo pendente. */
    atual: ChaveDoPasso | null;
    /** "Agora não": some do Início até amanhã. */
    adiado: boolean;
    /** O modal de boas-vindas ainda não foi visto (só no primeiro acesso). */
    mostrar_modal: boolean;
    passos: PassoDoRoteiro[];
    contagens: { espacos: number; equipamentos: number; grupos: number };
    /** Null até a agenda ganhar o endereço (o Início garante). */
    link_do_formulario: string | null;
};
