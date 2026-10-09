import type { GruposFontes, PesosDasFontes } from '@/types/certificados';

const ROTULOS_DE_PESO: Record<number, string> = {
    100: 'Thin',
    200: 'Extra Light',
    300: 'Light',
    400: 'Regular',
    500: 'Medium',
    600: 'Semi Bold',
    700: 'Bold',
    800: 'Extra Bold',
    900: 'Black',
};

export function rotuloDoPeso(peso: number): string {
    return ROTULOS_DE_PESO[peso] ?? String(peso);
}

/** Mesma regra do servidor: o peso mais próximo; no empate, o mais pesado. */
export function pesoMaisProximo(pesos: number[], pedido: number): number {
    return pesos.reduce((melhor, peso) => {
        const distancia = Math.abs(peso - pedido);
        const melhorDistancia = Math.abs(melhor - pedido);

        return distancia < melhorDistancia ||
            (distancia === melhorDistancia && peso > melhor)
            ? peso
            : melhor;
    }, pesos[0] ?? pedido);
}

export function pastaDaFamilia(
    fontes: GruposFontes,
    familia: string,
): string | undefined {
    for (const familias of Object.values(fontes)) {
        if (familia in familias) {
            return familias[familia];
        }
    }

    return undefined;
}

/** Pesos que a família tem; se a pasta não tem arquivos, só o peso padrão. */
export function pesosDaFamilia(
    fontes: GruposFontes,
    pesos: PesosDasFontes,
    familia: string,
    padrao: number,
): number[] {
    const pasta = pastaDaFamilia(fontes, familia);
    const disponiveis = pasta ? (pesos[pasta] ?? []) : [];

    return disponiveis.length > 0 ? disponiveis : [padrao];
}

/** CSS com um @font-face por peso que tem arquivo, apontando para as URLs do próprio servidor. */
export function gerarFontFaces(
    fontes: GruposFontes,
    pesos: PesosDasFontes,
    urlDaFonte: (pasta: string, peso: number) => string,
): string {
    const regras: string[] = [];

    for (const familias of Object.values(fontes)) {
        for (const [familia, pasta] of Object.entries(familias)) {
            for (const peso of pesos[pasta] ?? []) {
                regras.push(
                    `@font-face{font-family:"${familia}";font-weight:${peso};font-style:normal;font-display:swap;src:url("${urlDaFonte(pasta, peso)}") format("truetype");}`,
                );
            }
        }
    }

    return regras.join('\n');
}
