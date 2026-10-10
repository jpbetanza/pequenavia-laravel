import { describe, expect, it } from 'vite-plus/test';
import { unzipSync } from 'fflate';
import { nomeDoZip, nomesUnicos, zipar } from './zip';

describe('nomesUnicos', () => {
    it('mantém os nomes que não repetem', () => {
        expect(nomesUnicos(['a.png', 'b.png'])).toEqual(['a.png', 'b.png']);
    });

    it('acrescenta -2, -3 antes da extensão quando o nome repete', () => {
        expect(nomesUnicos(['a.png', 'a.png', 'a.png'])).toEqual([
            'a.png',
            'a-2.png',
            'a-3.png',
        ]);
    });

    it('trata nomes sem extensão e não repete um nome já gerado', () => {
        expect(
            nomesUnicos(['certificado', 'certificado', 'certificado-2']),
        ).toEqual(['certificado', 'certificado-2', 'certificado-2-2']);
    });
});

describe('zipar', () => {
    it('empacota dois blobs, com nomes repetidos únicos, sem alterar o conteúdo', async () => {
        const zip = await zipar([
            {
                nome: 'Maria.png',
                blob: new Blob(['um'], { type: 'image/png' }),
            },
            {
                nome: 'Maria.png',
                blob: new Blob(['dois'], { type: 'image/png' }),
            },
        ]);

        const dados = unzipSync(new Uint8Array(await zip.arrayBuffer()));

        expect(Object.keys(dados).sort()).toEqual(['Maria-2.png', 'Maria.png']);
        expect(new TextDecoder().decode(dados['Maria.png'])).toBe('um');
        expect(new TextDecoder().decode(dados['Maria-2.png'])).toBe('dois');
        expect(zip.type).toBe('application/zip');
    });
});

describe('nomeDoZip', () => {
    it('monta certificados-{modelo}.zip trocando caracteres inseguros por traço', () => {
        expect(nomeDoZip('Batismo 2026')).toBe('certificados-Batismo 2026.zip');
        expect(nomeDoZip('Casa/Noivos: 1')).toBe(
            'certificados-Casa-Noivos- 1.zip',
        );
    });

    it('usa um nome padrão quando o título fica vazio', () => {
        expect(nomeDoZip('  ')).toBe('certificados.zip');
    });
});
