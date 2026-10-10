import { tempoRelativo } from '@/lib/tempo';

/** "Recebida há 2 horas", ou "Recebida agora mesmo". Null quando a data não existe. */
export function rotuloDeRecebida(
    recebidaEm: string | null,
    agora: Date = new Date(),
): string | null {
    const tempo = tempoRelativo(recebidaEm, agora);

    if (tempo === null) {
        return null;
    }

    return tempo === 'agora mesmo'
        ? 'Recebida agora mesmo'
        : `Recebida ${tempo}`;
}
