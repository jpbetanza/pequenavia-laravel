import { describe, expect, it } from 'vite-plus/test';
import {
    chaveEmBytes,
    corpoDaInscricao,
    descreverAparelho,
    impedimento,
} from './notificacoes';

const IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
const ANDROID_CHROME =
    'Mozilla/5.0 (Linux; Android 15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36';
const SAMSUNG =
    'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0 Mobile Safari/537.36';
const MAC_FIREFOX =
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 14.6; rv:131.0) Gecko/20100101 Firefox/131.0';

describe('chaveEmBytes', () => {
    it('decodifica base64url sem preenchimento, como a chave VAPID vem do servidor', () => {
        // "Olá!" em UTF-8: 4f 6c c3 a1 21 → base64url "T2zDoSE".
        expect([...chaveEmBytes('T2zDoSE')]).toEqual([
            0x4f, 0x6c, 0xc3, 0xa1, 0x21,
        ]);
        // Bytes que viram "-" e "_" no base64url.
        expect([...chaveEmBytes('-_8')]).toEqual([0xfb, 0xff]);
    });
});

describe('descreverAparelho', () => {
    it('dá um nome curto ao aparelho e ao navegador', () => {
        expect(descreverAparelho(IPHONE)).toBe('iPhone · Safari');
        expect(descreverAparelho(ANDROID_CHROME)).toBe('Android · Chrome');
        expect(descreverAparelho(SAMSUNG)).toBe('Android · Samsung Internet');
        expect(descreverAparelho(MAC_FIREFOX)).toBe('Computador · Firefox');
    });
});

describe('impedimento', () => {
    const base = {
        suporta: true,
        iphone: false,
        instalado: false,
        permissao: 'default' as NotificationPermission,
    };

    it('no iPhone fora do app instalado, pede para instalar antes de tudo', () => {
        expect(impedimento({ ...base, iphone: true, suporta: false })).toBe(
            'instalar-no-iphone',
        );
        expect(impedimento({ ...base, iphone: true, instalado: true })).toBe(
            null,
        );
    });

    it('sem suporte ou com a permissão negada, explica o motivo', () => {
        expect(impedimento({ ...base, suporta: false })).toBe('sem-suporte');
        expect(impedimento({ ...base, permissao: 'denied' })).toBe(
            'bloqueadas',
        );
        expect(impedimento(base)).toBe(null);
    });
});

describe('corpoDaInscricao', () => {
    it('manda o endpoint, as chaves e o nome do aparelho', () => {
        expect(
            corpoDaInscricao(
                {
                    endpoint: 'https://fcm.googleapis.com/fcm/send/abc',
                    keys: { p256dh: 'chave', auth: 'segredo' },
                },
                ANDROID_CHROME,
            ),
        ).toEqual({
            endpoint: 'https://fcm.googleapis.com/fcm/send/abc',
            keys: { p256dh: 'chave', auth: 'segredo' },
            dispositivo: 'Android · Chrome',
        });
    });
});
