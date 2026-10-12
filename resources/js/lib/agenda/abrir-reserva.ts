/** Parâmetros que o painel do assistente põe na URL do calendário para abrir uma nova reserva. */
const PARAMETROS = ['nova_reserva', 'espaco', 'inicio'] as const;

const HORARIO = /^([01]\d|2[0-3]):[0-5]\d$/;

export type PedidoDeNovaReserva = {
    espacoId: string | null;
    /** Horário HH:MM, ou null quando ausente ou inválido. */
    inicio: string | null;
};

/** Lê o pedido de nova reserva da query da página. Devolve null quando `nova_reserva` não é 1. */
export function lerPedidoDeNovaReserva(
    search: string,
): PedidoDeNovaReserva | null {
    const parametros = new URLSearchParams(search);

    if (parametros.get('nova_reserva') !== '1') {
        return null;
    }

    const inicio = parametros.get('inicio');
    const espaco = parametros.get('espaco');

    return {
        espacoId: espaco === null || espaco === '' ? null : espaco,
        inicio: inicio !== null && HORARIO.test(inicio) ? inicio : null,
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
