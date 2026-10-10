import { describe, expect, it } from 'vite-plus/test';
import { linkDoWhatsApp, MENSAGEM_DO_WHATSAPP } from './whatsapp';

describe('linkDoWhatsApp', () => {
    it('acrescenta 55 quando o número não traz o código do país', () => {
        expect(linkDoWhatsApp('(11) 90000-0000')).toBe(
            `https://wa.me/5511900000000?text=${encodeURIComponent(MENSAGEM_DO_WHATSAPP)}`,
        );
        expect(linkDoWhatsApp('1190000000')).toMatch(
            /^https:\/\/wa\.me\/551190000000\?/,
        );
    });

    it('mantém o número quando já traz 55', () => {
        expect(linkDoWhatsApp('+55 11 90000-0000')).toMatch(
            /^https:\/\/wa\.me\/5511900000000\?/,
        );
    });

    it('usa a mensagem fixa, com a acentuação codificada', () => {
        const link = linkDoWhatsApp('11900000000') ?? '';

        expect(link).toContain('text=Ol%C3%A1!');
        expect(link).toContain(encodeURIComponent('pedido de reserva.'));
    });

    it('devolve null para número curto ou longo demais', () => {
        expect(linkDoWhatsApp('123456789')).toBeNull();
        expect(linkDoWhatsApp('12345678901234')).toBeNull();
        expect(linkDoWhatsApp('')).toBeNull();
    });
});
