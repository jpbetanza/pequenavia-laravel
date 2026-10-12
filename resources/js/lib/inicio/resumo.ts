import type { PainelDeHoje } from '@/types/agendamentos';

export type Periodo = 'manha' | 'tarde' | 'noite';

const SAUDACAO_DO_PERIODO: Record<Periodo, string> = {
    manha: 'Bom dia',
    tarde: 'Boa tarde',
    noite: 'Boa noite',
};

/** Primeira palavra do nome, sem espaços nas pontas. Vazio quando não há nome. */
export function primeiroNome(nome: string): string {
    return nome.trim().split(/\s+/)[0] ?? '';
}

export function saudacao(periodo: Periodo, nome: string): string {
    const prefixo = SAUDACAO_DO_PERIODO[periodo];
    const primeiro = primeiroNome(nome);

    return primeiro === '' ? `${prefixo}.` : `${prefixo}, ${primeiro}.`;
}

type ContagemDoDia = { reservas: number; pedidos: number; separar: number };

function plural(quantidade: number, singular: string, plural: string): string {
    return `${quantidade} ${quantidade === 1 ? singular : plural}`;
}

/** Junta as partes como "a, b e c". */
function juntar(partes: string[]): string {
    if (partes.length <= 1) {
        return partes.join('');
    }

    return `${partes.slice(0, -1).join(', ')} e ${partes.at(-1)}`;
}

export function resumoDoDia({
    reservas,
    pedidos,
    separar,
}: ContagemDoDia): string {
    const partes: string[] = [];

    if (reservas > 0) {
        partes.push(`${plural(reservas, 'reserva', 'reservas')} hoje`);
    }

    if (pedidos > 0) {
        partes.push(
            `${plural(pedidos, 'pedido', 'pedidos')} esperando resposta`,
        );
    }

    if (separar > 0) {
        partes.push(
            `${plural(separar, 'equipamento', 'equipamentos')} para separar`,
        );
    }

    if (partes.length === 0) {
        return 'Nenhuma reserva hoje e nenhum pedido esperando resposta.';
    }

    return `${juntar(partes)}.`;
}

/** Itens de levar ainda não entregues, somados em unidades. Recolher não conta. */
export function equipamentosParaSeparar(painel: PainelDeHoje): number {
    return painel.movimentos
        .filter((movimento) => movimento.acao === 'levar')
        .flatMap((movimento) => movimento.itens)
        .filter((item) => !item.marcado)
        .reduce((total, item) => total + item.quantidade, 0);
}

/** "15:00" vira "15h"; "19:30" vira "19h30". */
export function horaCurta(hora: string): string {
    const [horas, minutos] = hora.split(':');
    const hh = Number(horas);

    return minutos === '00' ? `${hh}h` : `${hh}h${minutos}`;
}

/** "ter 20": dia da semana curto e dia do mês, sem o ponto do pt-BR. Recebe "YYYY-MM-DD". */
export function dataCurta(data: string): string {
    const partes = new Intl.DateTimeFormat('pt-BR', {
        weekday: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    }).formatToParts(new Date(`${data}T00:00:00Z`));

    const diaDaSemana =
        partes.find((parte) => parte.type === 'weekday')?.value ?? '';
    const dia = partes.find((parte) => parte.type === 'day')?.value ?? '';

    return `${diaDaSemana.replace('.', '')} ${dia}`;
}

/** Nome do mês em minúsculas, a partir de uma data "YYYY-MM-DD". */
export function mesPorExtenso(data: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        month: 'long',
        timeZone: 'UTC',
    }).format(new Date(`${data}T00:00:00Z`));
}

/** Dia anterior de uma data "YYYY-MM-DD". */
function diaAnterior(data: string): string {
    const anterior = new Date(`${data}T00:00:00Z`);

    anterior.setUTCDate(anterior.getUTCDate() - 1);

    return anterior.toISOString().slice(0, 10);
}

/** "hoje", "ontem" ou "dd/mm", pela data no fuso da agenda (o servidor já a manda nesse fuso). */
export function diaDoCertificado(geradoEm: string, dataHoje: string): string {
    const dia = geradoEm.slice(0, 10);

    if (dia === dataHoje) {
        return 'hoje';
    }

    if (dia === diaAnterior(dataHoje)) {
        return 'ontem';
    }

    return `${dia.slice(8, 10)}/${dia.slice(5, 7)}`;
}

type ResumoDeCertificados = {
    emitidos: number;
    dataHoje: string;
    ultimo: { modelo: string; gerado_em: string } | null;
};

export function linhaDeCertificados({
    emitidos,
    dataHoje,
    ultimo,
}: ResumoDeCertificados): string {
    const mes = mesPorExtenso(dataHoje);
    const primeira =
        emitidos === 0
            ? `Nenhum emitido em ${mes}`
            : `${plural(emitidos, 'emitido', 'emitidos')} em ${mes}`;

    if (ultimo === null) {
        return primeira;
    }

    return `${primeira} · último: ${ultimo.modelo}, ${diaDoCertificado(ultimo.gerado_em, dataHoje)}`;
}
