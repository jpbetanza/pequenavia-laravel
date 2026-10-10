import { Zip, ZipPassThrough } from 'fflate';

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
 *
 * Cada arquivo é lido uma vez e entra no zip em streaming: as partes do zip vão direto para o Blob,
 * sem montar um Uint8Array com o zip inteiro e copiá-lo depois.
 */
export async function zipar(arquivos: ArquivoParaZip[]): Promise<Blob> {
    const nomes = nomesUnicos(arquivos.map((arquivo) => arquivo.nome));
    const partes: Uint8Array<ArrayBuffer>[] = [];
    const falhas: Error[] = [];
    const zip = new Zip((falha, parte) => {
        if (falha) {
            falhas.push(falha);
        } else {
            // O fflate devolve um Uint8Array sobre ArrayBuffer comum; o tipo estreito só satisfaz o Blob.
            partes.push(parte as Uint8Array<ArrayBuffer>);
        }
    });

    for (const [indice, arquivo] of arquivos.entries()) {
        const entrada = new ZipPassThrough(nomes[indice]);

        zip.add(entrada);
        entrada.push(new Uint8Array(await arquivo.blob.arrayBuffer()), true);
    }

    zip.end();

    if (falhas.length > 0) {
        throw falhas[0];
    }

    return new Blob(partes, { type: 'application/zip' });
}
