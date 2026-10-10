import { zipSync } from 'fflate';

export type ArquivoParaZip = {
    nome: string;
    blob: Blob;
};

/** Nomes únicos: o repetido ganha sufixo `-2`, `-3`… antes da extensão. */
export function nomesUnicos(nomes: string[]): string[] {
    const usados = new Set<string>();

    return nomes.map((nome) => {
        if (!usados.has(nome)) {
            usados.add(nome);

            return nome;
        }

        const ponto = nome.lastIndexOf('.');
        const base = ponto > 0 ? nome.slice(0, ponto) : nome;
        const extensao = ponto > 0 ? nome.slice(ponto) : '';
        let numero = 2;

        while (usados.has(`${base}-${numero}${extensao}`)) {
            numero++;
        }

        const unico = `${base}-${numero}${extensao}`;
        usados.add(unico);

        return unico;
    });
}

/** Nome do .zip dos certificados: `certificados-{modelo}.zip`, trocando o que não é seguro em arquivo por "-". */
export function nomeDoZip(titulo: string): string {
    const seguro = titulo.replace(/[^\p{L}\p{N} _.-]/gu, '-').trim();

    return seguro ? `certificados-${seguro}.zip` : 'certificados.zip';
}

/**
 * Empacota os arquivos num .zip sem compressão (PNG já é comprimido). Nomes repetidos viram `-2`, `-3`…
 */
export async function zipar(arquivos: ArquivoParaZip[]): Promise<Blob> {
    const nomes = nomesUnicos(arquivos.map((arquivo) => arquivo.nome));
    const conteudo: Record<string, Uint8Array> = {};

    for (const [indice, arquivo] of arquivos.entries()) {
        conteudo[nomes[indice]] = new Uint8Array(
            await arquivo.blob.arrayBuffer(),
        );
    }

    return new Blob([zipSync(conteudo, { level: 0 })], {
        type: 'application/zip',
    });
}
