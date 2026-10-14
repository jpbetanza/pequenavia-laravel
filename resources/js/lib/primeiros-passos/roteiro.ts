import type {
    ChaveDoPasso,
    PassoDoRoteiro,
    PrimeirosPassos,
} from '@/types/primeiros-passos';

/** O que "Configurar com o assistente" manda: o assistente conduz o roteiro a partir do primeiro passo pendente. */
export const PEDIDO_DO_ROTEIRO =
    'Quero configurar a agenda pelos primeiros passos. Vamos começar?';

/** Texto que acompanha um arquivo solto no banner ou numa tela vazia. */
export const PEDIDO_DO_ARQUIVO =
    'Cadastre o que está neste arquivo. Antes, me diga o que entendeu.';

const PEDIDOS: Record<ChaveDoPasso, string> = {
    espacos: 'Quero cadastrar os espaços da paróquia.',
    equipamentos: 'Quero cadastrar os equipamentos.',
    grupos: 'Quero cadastrar os grupos e pastorais.',
    formulario:
        'Me passe o link do formulário de pedidos e escreva uma mensagem curta para o WhatsApp dos coordenadores.',
    avisos: 'Como ativo os avisos de novos pedidos no meu celular?',
};

/** O que "Pedir ao assistente" manda para cada passo. */
export function pedidoDoPasso(chave: ChaveDoPasso): string {
    return PEDIDOS[chave];
}

/** Texto que a tela vazia de um cadastro manda junto com a descrição digitada. */
export function pedidoDaDescricao(
    cadastro: 'espacos' | 'equipamentos' | 'grupos',
    descricao: string,
): string {
    const prefixo = {
        espacos: 'Cadastre estes espaços',
        equipamentos: 'Cadastre estes equipamentos',
        grupos: 'Cadastre estes grupos e pastorais',
    }[cadastro];

    return `${prefixo}: ${descricao.trim()}`;
}

/** Resolvido: feito ou pulado. */
export function resolvido(passo: PassoDoRoteiro): boolean {
    return passo.feito || passo.pulado;
}

/** Porcentagem da barra de progresso. */
export function porcentagem(roteiro: PrimeirosPassos): number {
    return roteiro.total === 0
        ? 100
        : Math.round((roteiro.feitos / roteiro.total) * 100);
}

/** Posição do passo no roteiro, a partir de 1. */
export function numeroDoPasso(
    roteiro: PrimeirosPassos,
    chave: ChaveDoPasso,
): number {
    return roteiro.passos.findIndex((passo) => passo.chave === chave) + 1;
}

/**
 * Resumo dos cadastros feitos para os selos: "3 espaços", "1 equipamento". Só o que tem pelo menos um.
 */
export function selosDosCadastros(
    contagens: PrimeirosPassos['contagens'],
): string[] {
    const nomes: Record<keyof PrimeirosPassos['contagens'], [string, string]> =
        {
            espacos: ['espaço', 'espaços'],
            equipamentos: ['equipamento', 'equipamentos'],
            grupos: ['grupo', 'grupos'],
        };

    return (
        Object.entries(contagens) as [
            keyof PrimeirosPassos['contagens'],
            number,
        ][]
    )
        .filter(([, total]) => total > 0)
        .map(
            ([chave, total]) =>
                `${total} ${total === 1 ? nomes[chave][0] : nomes[chave][1]}`,
        );
}
