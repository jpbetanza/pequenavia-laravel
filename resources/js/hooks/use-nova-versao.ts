import { useSyncExternalStore } from 'react';
import { assinar, lerEstado } from '@/lib/versao/nova-versao';

/** Estado do aviso de nova versão, compartilhado entre o aviso e o selo do topo. */
export function useNovaVersao() {
    return useSyncExternalStore(assinar, lerEstado, lerEstado);
}
