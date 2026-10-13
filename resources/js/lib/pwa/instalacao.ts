/**
 * Instalação do PequenaVia como aplicativo (PWA).
 *
 * Android e Chrome avisam que o site pode ser instalado com o evento `beforeinstallprompt`, que guardamos para o
 * botão "Instalar" abrir o pedido do sistema depois. O iPhone não tem esse evento: lá o banner ensina a usar
 * Compartilhar → Adicionar à Tela de Início.
 */

/** Evento do Chrome ainda sem tipo no TypeScript. */
export type PedidoDeInstalacao = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

/** Depois de "Agora não", o banner volta só depois desse tempo. */
export const DIAS_SEM_BANNER = 30;

const CHAVE_DA_DISPENSA = 'pequenavia:instalacao-dispensada-em';

let pedido: PedidoDeInstalacao | null = null;
let instalado = false;
const ouvintes = new Set<() => void>();

function avisar() {
    ouvintes.forEach((ouvinte) => ouvinte());
}

/**
 * Começa a ouvir os eventos de instalação e registra o service worker. Chame uma vez, o quanto antes:
 * o `beforeinstallprompt` pode chegar antes de o banner existir.
 */
export function prepararInstalacao(janela: Window = window): void {
    janela.addEventListener('beforeinstallprompt', (evento) => {
        // Sem isso o Chrome mostra a própria barra; quem oferece é o nosso banner.
        evento.preventDefault();
        pedido = evento as PedidoDeInstalacao;
        avisar();
    });

    janela.addEventListener('appinstalled', () => {
        pedido = null;
        instalado = true;
        avisar();
    });

    if ('serviceWorker' in janela.navigator) {
        janela.addEventListener('load', () => {
            void janela.navigator.serviceWorker.register('/sw.js').catch(() => {
                // Sem service worker o site funciona igual; só não fica instalável no Chrome.
            });
        });
    }
}

export function assinarInstalacao(ouvinte: () => void): () => void {
    ouvintes.add(ouvinte);

    return () => ouvintes.delete(ouvinte);
}

export function pedidoDeInstalacao(): PedidoDeInstalacao | null {
    return pedido;
}

export function foiInstalado(): boolean {
    return instalado;
}

/** Abre o pedido de instalação do sistema. Devolve se a pessoa aceitou. */
export async function instalar(): Promise<boolean> {
    const atual = pedido;

    if (atual === null) {
        return false;
    }

    // O pedido só pode ser usado uma vez.
    pedido = null;
    avisar();
    await atual.prompt();

    return (await atual.userChoice).outcome === 'accepted';
}

/** Já está aberto como aplicativo instalado (tela cheia, sem a barra do navegador)? */
export function rodandoComoAplicativo(janela: Window = window): boolean {
    return (
        janela.matchMedia('(display-mode: standalone)').matches ||
        (janela.navigator as Navigator & { standalone?: boolean })
            .standalone === true
    );
}

/** iPhone ou iPad no Safari: lá a instalação é manual, pelo menu Compartilhar. */
export function ehSafariDoIos(userAgent: string, toques = 0): boolean {
    const ios =
        /iPhone|iPad|iPod/.test(userAgent) ||
        // iPad com iPadOS se apresenta como Mac, mas tem tela de toque.
        (/Macintosh/.test(userAgent) && toques > 1);
    // Chrome, Firefox e Edge no iOS não instalam PWA pelo próprio menu.
    const outroNavegador = /CriOS|FxiOS|EdgiOS|OPiOS/.test(userAgent);

    return ios && !outroNavegador && /Safari/.test(userAgent);
}

function armazenamento(): Storage | null {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

/** "Agora não" foi tocado há menos de `DIAS_SEM_BANNER` dias? */
export function dispensadoRecentemente(
    agora: number = Date.now(),
    local: Storage | null = armazenamento(),
): boolean {
    const quando = Number(local?.getItem(CHAVE_DA_DISPENSA) ?? Number.NaN);

    if (!Number.isFinite(quando)) {
        return false;
    }

    return agora - quando < DIAS_SEM_BANNER * 24 * 60 * 60 * 1000;
}

export function dispensar(
    agora: number = Date.now(),
    local: Storage | null = armazenamento(),
): void {
    try {
        local?.setItem(CHAVE_DA_DISPENSA, String(agora));
    } catch {
        // Armazenamento bloqueado: o banner volta na próxima visita, sem problema.
    }
}
