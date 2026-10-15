/**
 * Avisa quando há uma versão nova do site no ar: compara a versão com que a página abriu com a do servidor
 * (/versao), de tempos em tempos e quando a aba volta a ficar visível. Estado compartilhado entre o aviso e o
 * selo do topo, lido com useSyncExternalStore.
 */

export type EstadoDaVersao = {
    /** Há uma versão nova no servidor. */
    nova: boolean;
    /** A pessoa escolheu "Depois": o aviso some e fica só o lembrete. */
    adiado: boolean;
    /** "Atualizar" foi clicado: a página está recarregando. */
    atualizando: boolean;
};

/** De quanto em quanto tempo perguntar ao servidor, com a aba visível. */
export const INTERVALO_MS = 5 * 60 * 1000;

let estado: EstadoDaVersao = { nova: false, adiado: false, atualizando: false };
const ouvintes = new Set<() => void>();

function mudar(parcial: Partial<EstadoDaVersao>) {
    estado = { ...estado, ...parcial };
    ouvintes.forEach((ouvinte) => ouvinte());
}

export function assinar(ouvinte: () => void): () => void {
    ouvintes.add(ouvinte);

    return () => ouvintes.delete(ouvinte);
}

export function lerEstado(): EstadoDaVersao {
    return estado;
}

/** A versão do servidor é outra? Versão vazia (resposta estranha) não conta como nova. */
export function ehVersaoNova(
    carregada: string,
    doServidor: string | null | undefined,
): boolean {
    return (
        typeof doServidor === 'string' &&
        doServidor !== '' &&
        doServidor !== carregada
    );
}

/** Pergunta a versão ao servidor. Sem rede ou com erro, não muda nada. */
export async function verificar(
    carregada: string,
    buscar: typeof fetch = fetch,
): Promise<void> {
    if (estado.nova) {
        return;
    }

    try {
        const resposta = await buscar('/versao', {
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });

        if (!resposta.ok) {
            return;
        }

        const corpo = (await resposta.json()) as { versao?: unknown };

        if (
            ehVersaoNova(
                carregada,
                typeof corpo.versao === 'string' ? corpo.versao : null,
            )
        ) {
            mudar({ nova: true });
        }
    } catch {
        // Sem conexão agora: tenta de novo na próxima vez.
    }
}

/** Começa a vigiar. Devolve a função que para. */
export function vigiar(carregada: string): () => void {
    const talvez = () => {
        if (document.visibilityState === 'visible') {
            void verificar(carregada);
        }
    };
    const intervalo = window.setInterval(talvez, INTERVALO_MS);

    document.addEventListener('visibilitychange', talvez);
    window.addEventListener('focus', talvez);

    return () => {
        window.clearInterval(intervalo);
        document.removeEventListener('visibilitychange', talvez);
        window.removeEventListener('focus', talvez);
    };
}

export function adiar() {
    mudar({ adiado: true });
}

export function reabrir() {
    mudar({ adiado: false });
}

/** Recarrega a página; antes, pede ao service worker do app instalado que se atualize também. */
export async function atualizar() {
    mudar({ atualizando: true, adiado: false });

    try {
        const registro = await navigator.serviceWorker?.getRegistration();
        await registro?.update();
    } catch {
        // Sem service worker (navegador comum): só recarregar já resolve.
    }

    window.location.reload();
}

/** Só para os testes: volta ao estado inicial. */
export function reiniciarParaTestes() {
    estado = { nova: false, adiado: false, atualizando: false };
}
