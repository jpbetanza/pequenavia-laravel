import { describe, expect, it } from 'vite-plus/test';
import { ErroHttp } from '@/lib/http';
import { chaveDaConversaSalva, deveDescartarConversa } from './conversa-salva';

describe('chaveDaConversaSalva', () => {
    it('separa a chave por usuário', () => {
        expect(chaveDaConversaSalva('7')).toBe('assistente-conversa-7');
        expect(chaveDaConversaSalva('7')).not.toBe(chaveDaConversaSalva('8'));
    });
});

describe('deveDescartarConversa', () => {
    it('descarta a referência só quando a conversa responde 404', () => {
        expect(
            deveDescartarConversa(new ErroHttp(404, 'Não encontrado.')),
        ).toBe(true);
    });

    it('mantém a referência em outros erros', () => {
        expect(deveDescartarConversa(new ErroHttp(422, 'x'))).toBe(false);
        expect(deveDescartarConversa(new ErroHttp(500, 'x'))).toBe(false);
        expect(deveDescartarConversa(new TypeError('rede'))).toBe(false);
        expect(deveDescartarConversa(undefined)).toBe(false);
    });
});
