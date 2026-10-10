import type {
    AcaoDeEquipamento,
    AgoraDoEquipamento,
} from '@/types/agendamentos';

/** Texto do estado de agora: "Em manutenção até 31/10", "1 em uso · Salão", "Livre" ou "4 livres". */
export function textoDoAgora(agora: AgoraDoEquipamento): string {
    if (agora.situacao === 'manutencao') {
        return `Em manutenção até ${agora.ate}`;
    }

    const espacos = agora.espacos.join(', ');
    const sufixo = espacos === '' ? '' : ` · ${espacos}`;

    if (agora.situacao === 'em_uso') {
        const emUso =
            agora.em_uso === agora.total ? 'Em uso' : `${agora.em_uso} em uso`;

        return `${emUso}${sufixo}`;
    }

    return agora.livres === 1 ? 'Livre' : `${agora.livres} livres`;
}

/** Letra do dia da semana como no artboard: S (seg.), T, Q, Q, S (sex.), S (sáb.), D. */
export function letraDoDia(data: string): string {
    const [ano, mes, dia] = data.split('-').map(Number);
    const letras = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];

    return letras[new Date(ano, mes - 1, dia).getDay()];
}

/** Tom da célula: cheia quando todas as unidades estão em uso no pico; parcial com algum uso; vazia sem uso. */
export function tomDaCelula(
    pico: number,
    quantidade: number,
): 'cheia' | 'parcial' | 'vazia' {
    if (pico > 0 && pico >= quantidade) {
        return 'cheia';
    }

    return pico > 0 ? 'parcial' : 'vazia';
}

/** Rótulo da ação no painel e no botão. */
export function rotuloDaAcao(acao: AcaoDeEquipamento): string {
    return acao === 'levar' ? 'Levar' : 'Recolher';
}

/** Texto do botão que marca a ação. */
export function rotuloDoMarcar(acao: AcaoDeEquipamento): string {
    return acao === 'levar' ? 'Marcar entregue' : 'Marcar recolhido';
}

/** Selo mostrado depois que a ação foi marcada. */
export function rotuloDoSelo(acao: AcaoDeEquipamento): string {
    return acao === 'levar' ? 'Entregue' : 'Recolhido';
}
