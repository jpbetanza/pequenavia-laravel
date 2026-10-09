import { useEffect } from 'react';
import { gerarFontFaces } from '@/lib/fontes';
import { show } from '@/routes/fontes';
import type { GruposFontes, PesosDasFontes } from '@/types/certificados';

/** Declara os @font-face do catálogo, com os mesmos arquivos que o servidor usa para gerar o PNG. */
export function useFontesDoCatalogo(
    fontes: GruposFontes,
    pesos: PesosDasFontes,
): void {
    useEffect(() => {
        const estilo = document.createElement('style');
        estilo.dataset.fontesDoCatalogo = 'true';
        estilo.textContent = gerarFontFaces(fontes, pesos, (pasta, peso) =>
            show.url({ pasta, peso }),
        );
        document.head.appendChild(estilo);

        return () => estilo.remove();
    }, [fontes, pesos]);
}
