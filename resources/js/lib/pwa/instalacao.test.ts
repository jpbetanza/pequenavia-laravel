import { describe, expect, it, vi } from 'vite-plus/test';
import {
    assinarInstalacao,
    DIAS_SEM_BANNER,
    dispensadoRecentemente,
    dispensar,
    ehSafariDoIos,
    foiInstalado,
    instalar,
    pedidoDeInstalacao,
    prepararInstalacao,
} from './instalacao';

const IPHONE_SAFARI =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
const IPHONE_CHROME =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/130.0 Mobile/15E148 Safari/604.1';
const IPAD_COMO_MAC =
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15';
const ANDROID_CHROME =
    'Mozilla/5.0 (Linux; Android 15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36';

/** Storage em memória, como o localStorage. */
function armazenamento(): Storage {
    const dados = new Map<string, string>();

    return {
        get length() {
            return dados.size;
        },
        clear: () => dados.clear(),
        getItem: (chave) => dados.get(chave) ?? null,
        key: (indice) => [...dados.keys()][indice] ?? null,
        removeItem: (chave) => void dados.delete(chave),
        setItem: (chave, valor) => void dados.set(chave, valor),
    };
}

describe('ehSafariDoIos', () => {
    it('reconhece o Safari do iPhone e do iPad, mas não outros navegadores', () => {
        expect(ehSafariDoIos(IPHONE_SAFARI)).toBe(true);
        expect(ehSafariDoIos(IPAD_COMO_MAC, 5)).toBe(true);
        expect(ehSafariDoIos(IPAD_COMO_MAC, 0)).toBe(false);
        expect(ehSafariDoIos(IPHONE_CHROME)).toBe(false);
        expect(ehSafariDoIos(ANDROID_CHROME)).toBe(false);
    });
});

describe('dispensa do banner', () => {
    it('vale por 30 dias depois de "Agora não"', () => {
        const local = armazenamento();
        const dia = 24 * 60 * 60 * 1000;
        const agora = Date.UTC(2026, 9, 13);

        expect(dispensadoRecentemente(agora, local)).toBe(false);

        dispensar(agora, local);

        expect(dispensadoRecentemente(agora + dia, local)).toBe(true);
        expect(
            dispensadoRecentemente(agora + DIAS_SEM_BANNER * dia, local),
        ).toBe(false);
    });

    it('sem armazenamento, nunca conta como dispensado', () => {
        expect(dispensadoRecentemente(Date.now(), null)).toBe(false);
        expect(() => dispensar(Date.now(), null)).not.toThrow();
    });
});

describe('pedido de instalação', () => {
    it('guarda o pedido do Chrome, avisa quem assina e usa o pedido uma vez só', async () => {
        const janela = new EventTarget() as unknown as Window;
        Object.assign(janela, { navigator: {} });
        prepararInstalacao(janela);

        const ouvinte = vi.fn();
        const parar = assinarInstalacao(ouvinte);
        const evento = Object.assign(
            new Event('beforeinstallprompt', { cancelable: true }),
            {
                prompt: vi.fn(() => Promise.resolve()),
                userChoice: Promise.resolve({ outcome: 'accepted' as const }),
            },
        );

        janela.dispatchEvent(evento);

        expect(evento.defaultPrevented).toBe(true);
        expect(pedidoDeInstalacao()).toBe(evento);
        expect(ouvinte).toHaveBeenCalled();

        await expect(instalar()).resolves.toBe(true);
        expect(evento.prompt).toHaveBeenCalledOnce();
        expect(pedidoDeInstalacao()).toBeNull();
        await expect(instalar()).resolves.toBe(false);

        janela.dispatchEvent(new Event('appinstalled'));
        expect(foiInstalado()).toBe(true);

        parar();
    });
});
