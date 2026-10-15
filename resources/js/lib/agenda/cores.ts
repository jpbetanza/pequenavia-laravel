/** Cores de identificação dos espaços. Único lugar com os hex da paleta: use `corDoEspaco`. */
export type CorDoEspaco =
    | 'bordo'
    | 'azul'
    | 'verde'
    | 'roxo'
    | 'dourado'
    | 'terracota'
    | 'petroleo'
    | 'oliva'
    | 'rosa'
    | 'grafite';

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
    terracota: {
        nome: 'Terracota',
        forte: '#9A4A2E',
        fundo: '#F4E1D8',
        texto: '#6E3320',
    },
    petroleo: {
        nome: 'Petróleo',
        forte: '#1E4E5A',
        fundo: '#DCEBEE',
        texto: '#163A43',
    },
    oliva: {
        nome: 'Oliva',
        forte: '#55551F',
        fundo: '#ECECD6',
        texto: '#40401A',
    },
    rosa: {
        nome: 'Rosa',
        forte: '#8A3A5C',
        fundo: '#F3DFE8',
        texto: '#682B45',
    },
    grafite: {
        nome: 'Grafite',
        forte: '#3F3F46',
        fundo: '#E6E6E8',
        texto: '#2F2F35',
    },
};

/** Ordem das amostras no seletor de cor. */
export const CORES_DOS_ESPACOS: readonly CorDoEspaco[] = [
    'bordo',
    'azul',
    'verde',
    'roxo',
    'dourado',
    'terracota',
    'petroleo',
    'oliva',
    'rosa',
    'grafite',
];

export function corDoEspaco(cor: CorDoEspaco): PaletaDoEspaco {
    return PALETAS[cor];
}
