import { diaDaSemana } from '@/lib/agenda/datas';

const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

/** "Sáb 18/10" a partir de uma data ISO AAAA-MM-DD. */
export function rotuloDoDia(iso: string): string {
    const [, mes, dia] = iso.split('-');

    return `${DIAS[diaDaSemana(iso)]} ${dia}/${mes}`;
}

/** "14:00–17:00". */
export function faixaDeHoras(inicio: string, fim: string): string {
    return `${inicio}–${fim}`;
}
