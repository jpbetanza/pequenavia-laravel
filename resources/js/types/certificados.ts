export type Campo = {
    id: string;
    modelo_id: string;
    nome: string;
    label: string | null;
    x: number;
    y: number;
    cor: string;
    tamanho_fonte: number;
    fonte_familia: string;
    peso_fonte: number;
    espacamento_letras: number;
    centralizado: boolean;
};

/** Grupo => (família exibida => pasta dos arquivos de fonte). */
export type GruposFontes = Record<string, Record<string, string>>;

/** Pasta => pesos que têm arquivo. */
export type PesosDasFontes = Record<string, number[]>;

export type ModeloBasico = {
    id: string;
    title: string;
    imagem_url: string;
};

export type Compartilhamento = {
    id: string;
    email: string;
    pode_editar: boolean;
};

export type ModeloResumo = {
    id: string;
    title: string;
    description: string | null;
    imagem_url: string;
    dono_nome: string;
    eh_dono: boolean;
    pode_editar: boolean;
    campos_count: number;
    compartilhamentos: Compartilhamento[];
};

export type Geracao = {
    id: string;
    modelo_id: string | null;
    modelo_title: string;
    campos_valores: Record<string, string>;
    ordem_campos: string[];
    created_at: string;
};
