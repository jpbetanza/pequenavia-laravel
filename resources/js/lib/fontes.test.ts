import { describe, expect, it } from 'vite-plus/test';
import {
    gerarFontFaces,
    pastaDaFamilia,
    pesoMaisProximo,
    pesosDaFamilia,
    rotuloDoPeso,
} from './fontes';

const fontes = {
    Serif: { Lora: 'lora', 'Dancing Script': 'dancing-script' },
    Sistema: { Georgia: 'gelasio' },
};

describe('pesoMaisProximo', () => {
    it.each([
        [[400, 700], 450, 400],
        [[400, 700], 650, 700],
        [[400, 700], 550, 700],
        [[400, 700], 100, 400],
        [[400, 700], 900, 700],
        [[400, 700], 700, 700],
        [[300], 600, 300],
    ])(
        'com os pesos %j e o pedido %d devolve %d (no empate vence o mais pesado)',
        (pesos, pedido, esperado) => {
            expect(pesoMaisProximo(pesos, pedido)).toBe(esperado);
        },
    );

    it('devolve o pedido quando não há nenhum peso', () => {
        expect(pesoMaisProximo([], 600)).toBe(600);
    });
});

describe('pastaDaFamilia', () => {
    it('acha a pasta em qualquer grupo', () => {
        expect(pastaDaFamilia(fontes, 'Georgia')).toBe('gelasio');
        expect(pastaDaFamilia(fontes, 'Dancing Script')).toBe('dancing-script');
    });

    it('devolve undefined para família fora do catálogo', () => {
        expect(pastaDaFamilia(fontes, 'Comic Sans MS')).toBeUndefined();
    });

    it('não confunde propriedades herdadas do objeto com famílias', () => {
        expect(pastaDaFamilia(fontes, 'constructor')).toBeUndefined();
        expect(pastaDaFamilia(fontes, 'toString')).toBeUndefined();
    });
});

describe('pesosDaFamilia', () => {
    it('devolve os pesos da pasta da família', () => {
        expect(
            pesosDaFamilia(fontes, { lora: [400, 700] }, 'Lora', 600),
        ).toEqual([400, 700]);
    });

    it('cai para o peso padrão quando a pasta não tem arquivos', () => {
        expect(pesosDaFamilia(fontes, { lora: [] }, 'Lora', 600)).toEqual([
            600,
        ]);
        expect(pesosDaFamilia(fontes, {}, 'Comic Sans MS', 600)).toEqual([600]);
    });
});

describe('rotuloDoPeso', () => {
    it('nomeia os pesos conhecidos e devolve o número para os demais', () => {
        expect(rotuloDoPeso(600)).toBe('Semi Bold');
        expect(rotuloDoPeso(450)).toBe('450');
    });
});

describe('gerarFontFaces', () => {
    const url = (pasta: string, peso: number) => `/fontes/${pasta}/${peso}.ttf`;

    it('gera um @font-face por peso que tem arquivo', () => {
        const css = gerarFontFaces(
            fontes,
            { lora: [400, 700], gelasio: [600] },
            url,
        );

        expect(css).toContain(
            '@font-face{font-family:"Lora";font-weight:400;font-style:normal;font-display:swap;src:url("/fontes/lora/400.ttf") format("truetype");}',
        );
        expect(css).toContain('font-family:"Lora";font-weight:700;');
        expect(css).toContain('font-family:"Georgia";font-weight:600;');
    });

    it('não gera nada para famílias sem arquivos', () => {
        const css = gerarFontFaces(
            fontes,
            { lora: [400], 'dancing-script': [] },
            url,
        );

        expect(css).not.toContain('Dancing Script');
        expect(css.match(/@font-face/g)).toHaveLength(1);
    });

    it('devolve texto vazio quando não há pesos', () => {
        expect(gerarFontFaces(fontes, {}, url)).toBe('');
    });
});
