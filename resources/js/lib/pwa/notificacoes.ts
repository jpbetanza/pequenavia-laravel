/**
 * Notificações no celular (Web Push): pede a permissão, inscreve o aparelho no serviço de push do navegador e
 * avisa o servidor. No iPhone só funciona com o app instalado na tela de início (iOS 16.4 ou mais novo).
 */

/** Por que este aparelho não pode receber notificações agora. `null`: pode. */
export type Impedimento =
    | 'sem-suporte'
    | 'instalar-no-iphone'
    | 'bloqueadas'
    | null;

/** O navegador tem service worker, push e notificações? */
export function suportaNotificacoes(janela: Window = window): boolean {
    return (
        'serviceWorker' in janela.navigator &&
        'PushManager' in janela &&
        'Notification' in janela
    );
}

/**
 * O que impede este aparelho de ativar. No iPhone fora do app instalado o Safari nem expõe o `PushManager`:
 * nesse caso a orientação é instalar, não "sem suporte".
 */
export function impedimento(opcoes: {
    suporta: boolean;
    iphone: boolean;
    instalado: boolean;
    permissao: NotificationPermission | null;
}): Impedimento {
    if (opcoes.iphone && !opcoes.instalado) {
        return 'instalar-no-iphone';
    }

    if (!opcoes.suporta) {
        return 'sem-suporte';
    }

    return opcoes.permissao === 'denied' ? 'bloqueadas' : null;
}

/** Chave VAPID em base64url (como o servidor guarda) para os bytes que o `pushManager.subscribe` pede. */
export function chaveEmBytes(base64url: string): Uint8Array<ArrayBuffer> {
    const preenchida = base64url.padEnd(
        base64url.length + ((4 - (base64url.length % 4)) % 4),
        '=',
    );
    const binario = atob(preenchida.replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(new ArrayBuffer(binario.length));

    for (let i = 0; i < binario.length; i++) {
        bytes[i] = binario.charCodeAt(i);
    }

    return bytes;
}

/** Nome curto do aparelho para a lista na tela: "iPhone · Safari", "Android · Chrome", "Computador · Firefox". */
export function descreverAparelho(userAgent: string): string {
    const sistema = /iPhone|iPod/.test(userAgent)
        ? 'iPhone'
        : /iPad/.test(userAgent)
          ? 'iPad'
          : /Android/.test(userAgent)
            ? 'Android'
            : 'Computador';

    const navegador = /EdgA?\//.test(userAgent)
        ? 'Edge'
        : /SamsungBrowser/.test(userAgent)
          ? 'Samsung Internet'
          : /Firefox|FxiOS/.test(userAgent)
            ? 'Firefox'
            : /Chrome|CriOS/.test(userAgent)
              ? 'Chrome'
              : /Safari/.test(userAgent)
                ? 'Safari'
                : 'navegador';

    return `${sistema} · ${navegador}`;
}

/** A inscrição deste navegador, se houver. */
export async function inscricaoAtual(): Promise<PushSubscription | null> {
    if (!suportaNotificacoes()) {
        return null;
    }

    const registro = await navigator.serviceWorker.getRegistration();

    return (await registro?.pushManager.getSubscription()) ?? null;
}

/** A pessoa negou a permissão (agora ou antes). */
export class PermissaoNegada extends Error {}

/**
 * Pede a permissão e inscreve o aparelho. Precisa ser chamado num toque (os navegadores exigem).
 * Devolve a inscrição, para o servidor guardar.
 *
 * @throws PermissaoNegada
 */
export async function inscreverAparelho(
    chavePublica: string,
): Promise<PushSubscription> {
    const permissao = await Notification.requestPermission();

    if (permissao !== 'granted') {
        throw new PermissaoNegada(
            'As notificações foram bloqueadas. Libere nas configurações do navegador para este site.',
        );
    }

    const registro =
        (await navigator.serviceWorker.getRegistration()) ??
        (await navigator.serviceWorker.register('/sw.js'));
    await navigator.serviceWorker.ready;

    return (
        (await registro.pushManager.getSubscription()) ??
        (await registro.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: chaveEmBytes(chavePublica),
        }))
    );
}

/** Corpo que o servidor espera para guardar a inscrição. */
export function corpoDaInscricao(
    inscricao: PushSubscriptionJSON,
    userAgent: string,
): {
    endpoint: string;
    keys: { p256dh: string; auth: string };
    dispositivo: string;
} {
    return {
        endpoint: inscricao.endpoint ?? '',
        keys: {
            p256dh: inscricao.keys?.p256dh ?? '',
            auth: inscricao.keys?.auth ?? '',
        },
        dispositivo: descreverAparelho(userAgent),
    };
}
