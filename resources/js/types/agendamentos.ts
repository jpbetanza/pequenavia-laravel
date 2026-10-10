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
