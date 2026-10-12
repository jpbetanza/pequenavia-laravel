/** Parâmetros que o painel do assistente põe na URL do calendário para abrir uma nova reserva. */
const PARAMETROS = [
    'nova_reserva',
    'espaco',
    'inicio',
    'titulo',
    'fim',
    'grupo',
    'equipamentos',
    'pessoas',
    'observacoes',
] as const;

const HORARIO = /^([01]\d|2[0-3]):[0-5]\d$/;
const QUANTIDADE = /^[1-9]\d{0,3}$/;
const TITULO_MAXIMO = 160;
const PESSOAS = /^[1-9]\d{0,3}$/;
const PESSOAS_MAXIMO = 2000;
const OBSERVACOES_MAXIMO = 1000;
const QUANTIDADE_MAXIMA = 1000;

export type EquipamentoPreenchido = { id: string; quantidade: number };

export type PedidoDeNovaReserva = {
    espacoId: string | null;
    /** Horário HH:MM, ou null quando ausente ou inválido. */
    inicio: string | null;
    /** Horário HH:MM de término, ou null quando ausente ou inválido. */
    fim: string | null;
    titulo: string | null;
    grupoId: string | null;
    /** Equipamentos na forma `id:quantidade,id:quantidade`. Inválidos e repetidos são ignorados. */
    equipamentos: EquipamentoPreenchido[];
    /** Inteiro de 1 a 2000, ou null quando ausente ou inválido. */
    pessoas: number | null;
    /** Texto aparado de até 1000 caracteres, ou null quando vazio, ausente ou longo demais. */
    observacoes: string | null;
};

/** Lê `id:quantidade,...`. Cada item precisa ter id não vazio e quantidade de 1 a 1000; o primeiro de um id repetido vale. */
function lerEquipamentos(valor: string | null): EquipamentoPreenchido[] {
    if (valor === null || valor === '') {
        return [];
    }

    const vistos = new Set<string>();
    const equipamentos: EquipamentoPreenchido[] = [];

    for (const item of valor.split(',')) {
        const partes = item.split(':');

        if (partes.length !== 2) {
            continue;
        }

        const [id, quantidade] = partes;

        if (
            id === '' ||
            !QUANTIDADE.test(quantidade) ||
            Number(quantidade) > QUANTIDADE_MAXIMA ||
            vistos.has(id)
        ) {
            continue;
        }

        vistos.add(id);
        equipamentos.push({ id, quantidade: Number(quantidade) });
    }

    return equipamentos;
}

function lerPessoas(valor: string | null): number | null {
    if (valor === null || !PESSOAS.test(valor)) {
        return null;
    }

    const pessoas = Number(valor);

    return pessoas <= PESSOAS_MAXIMO ? pessoas : null;
}

function lerObservacoes(valor: string | null): string | null {
    const texto = valor?.trim() ?? '';

    return texto === '' || texto.length > OBSERVACOES_MAXIMO ? null : texto;
}

/** Lê o pedido de nova reserva da query da página. Devolve null quando `nova_reserva` não é 1. */
export function lerPedidoDeNovaReserva(
    search: string,
): PedidoDeNovaReserva | null {
    const parametros = new URLSearchParams(search);

    if (parametros.get('nova_reserva') !== '1') {
        return null;
    }

    const inicio = parametros.get('inicio');
    const fim = parametros.get('fim');
    const espaco = parametros.get('espaco');
    const grupo = parametros.get('grupo');
    const titulo = parametros.get('titulo')?.trim() ?? '';

    return {
        espacoId: espaco === null || espaco === '' ? null : espaco,
        inicio: inicio !== null && HORARIO.test(inicio) ? inicio : null,
        fim: fim !== null && HORARIO.test(fim) ? fim : null,
        titulo: titulo === '' || titulo.length > TITULO_MAXIMO ? null : titulo,
        grupoId: grupo === null || grupo === '' ? null : grupo,
        equipamentos: lerEquipamentos(parametros.get('equipamentos')),
        pessoas: lerPessoas(parametros.get('pessoas')),
        observacoes: lerObservacoes(parametros.get('observacoes')),
    };
}

/** Devolve a URL sem os parâmetros de nova reserva, mantendo os demais. */
export function removerParametrosDeNovaReserva(href: string): string {
    const url = new URL(href);

    for (const parametro of PARAMETROS) {
        url.searchParams.delete(parametro);
    }

    return url.toString();
}
