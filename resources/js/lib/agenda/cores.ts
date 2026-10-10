/** Cores de identificação dos espaços. Único lugar com os hex da paleta: use `corDoEspaco`. */
export type CorDoEspaco = 'bordo' | 'azul' | 'verde' | 'roxo' | 'dourado';

export type PaletaDoEspaco = {
    /** Nome exibido e lido por leitor de tela. */
    nome: string;
    /** Cor principal: ícone, borda da amostra e destaque. */
    forte: string;
    /** Fundo suave: quadrado do ícone e chips em destaque. */
    fundo: string;
    /** Texto sobre o fundo suave. */
    texto: string;
};

const PALETAS: Record<CorDoEspaco, PaletaDoEspaco> = {
    bordo: {
        nome: 'Bordô',
        forte: '#6E1F24',
        fundo: '#F1DFDC',
        texto: '#521519',
    },
    azul: {
        nome: 'Azul',
        forte: '#1F2A44',
        fundo: '#DFE4EE',
        texto: '#1F2A44',
    },
    verde: {
        nome: 'Verde',
        forte: '#24513F',
        fundo: '#E2EEE8',
        texto: '#1B3D30',
    },
    roxo: {
        nome: 'Roxo',
        forte: '#4B2E5C',
        fundo: '#EADFF0',
        texto: '#3A2148',
    },
    dourado: {
        nome: 'Dourado',
        forte: '#7A5A1E',
        fundo: '#F3E8CF',
        texto: '#5A4214',
    },
};

/** Ordem das amostras no seletor de cor. */
export const CORES_DOS_ESPACOS: readonly CorDoEspaco[] = [
    'bordo',
    'azul',
    'verde',
    'roxo',
    'dourado',
];

export function corDoEspaco(cor: CorDoEspaco): PaletaDoEspaco {
    return PALETAS[cor];
}
