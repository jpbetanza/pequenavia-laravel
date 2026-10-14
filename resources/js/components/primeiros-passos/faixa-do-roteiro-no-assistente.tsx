import { usePage } from '@inertiajs/react';
import { aplicarAoRoteiro } from '@/lib/primeiros-passos/acoes';
import { numeroDoPasso } from '@/lib/primeiros-passos/roteiro';

/**
 * Faixa presa no topo da conversa enquanto o roteiro de primeiros passos não termina: em que passo está e "Pular passo".
 * Com o roteiro adiado, dispensado ou concluído, não aparece.
 */
export function FaixaDoRoteiroNoAssistente() {
    const { primeiros_passos: roteiro } = usePage().props;

    if (
        roteiro === null ||
        roteiro.adiado ||
        roteiro.fase === 'concluido' ||
        roteiro.atual === null
    ) {
        return null;
    }

    const atual = roteiro.atual;
    const passo = roteiro.passos.find((item) => item.chave === atual);

    return (
        <div className="flex items-center gap-2.5 border-b bg-aviso-fundo py-1 pr-2 pl-5 text-sm text-aviso">
            <span className="min-w-0 flex-1 truncate font-semibold">
                Primeiros passos · {numeroDoPasso(roteiro, atual)} de{' '}
                {roteiro.total}: {passo?.titulo}
            </span>
            <button
                type="button"
                onClick={() => aplicarAoRoteiro('pular', atual)}
                className="min-h-11 shrink-0 px-2 font-semibold underline underline-offset-4"
            >
                Pular passo
            </button>
        </div>
    );
}
