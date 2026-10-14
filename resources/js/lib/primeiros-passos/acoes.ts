import { router } from '@inertiajs/react';
import PrimeirosPassosController from '@/actions/App/Http/Controllers/PrimeirosPassosController';
import type { ChaveDoPasso } from '@/types/primeiros-passos';

export type AcaoDoRoteiro = 'pular' | 'adiar' | 'dispensar' | 'link_enviado';

/** Grava uma escolha no roteiro e recarrega as props, sem mexer na rolagem nem no estado da página. */
export function aplicarAoRoteiro(acao: AcaoDoRoteiro, passo?: ChaveDoPasso) {
    router.post(
        PrimeirosPassosController.update.url(),
        { acao, ...(passo ? { passo } : {}) },
        { preserveScroll: true, preserveState: true },
    );
}
