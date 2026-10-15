import type { Auth } from '@/types/auth';
import type { GestaoCompartilhado } from '@/types/gestao';
import type { PrimeirosPassos } from '@/types/primeiros-passos';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            /** Versão do site no ar (SHA do commit ou impressão do manifesto). */
            versao: string;
            auth: Auth;
            sidebarOpen: boolean;
            agenda: { solicitacoes_pendentes: number } | null;
            assistente: { disponivel: boolean } | null;
            primeiros_passos: PrimeirosPassos | null;
            gestao: GestaoCompartilhado | null;
            [key: string]: unknown;
        };
    }
}
