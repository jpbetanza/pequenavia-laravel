import { usePage } from '@inertiajs/react';
import { RotateCw, Sparkles, X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useNovaVersao } from '@/hooks/use-nova-versao';
import { adiar, atualizar, reabrir, vigiar } from '@/lib/versao/nova-versao';
import { cn } from '@/lib/utils';

/**
 * Pílula escura que aparece quando há uma versão nova do site no ar: "Atualizar" recarrega a página.
 * No computador fica no canto inferior esquerdo; no celular, centralizada embaixo. "Depois" esconde e deixa
 * um lembrete (o selo do topo no computador, um botão redondo no celular).
 */
export function AvisoDeNovaVersao() {
    const { versao } = usePage().props;
    // A versão com que a página abriu: as visitas seguintes do Inertia não mudam a referência.
    const carregada = useRef(versao);
    const { nova, adiado, atualizando } = useNovaVersao();

    useEffect(() => vigiar(carregada.current), []);

    if (!nova) {
        return null;
    }

    if (adiado) {
        return (
            <button
                type="button"
                onClick={reabrir}
                aria-label="Nova versão disponível: atualizar"
                className="fixed right-4 bottom-[calc(env(safe-area-inset-bottom)+1rem)] z-40 flex size-13 items-center justify-center rounded-full bg-foreground text-dourado-claro shadow-[0_10px_28px_rgba(34,26,23,0.3)] md:hidden"
            >
                <RotateCw aria-hidden="true" className="size-5" />
                <span
                    aria-hidden="true"
                    className="absolute top-2 right-2 size-2.5 rounded-full border-2 border-foreground bg-dourado-claro"
                />
            </button>
        );
    }

    return (
        <section
            role="status"
            aria-live="polite"
            aria-label={
                atualizando
                    ? 'Atualizando: recarregando a página.'
                    : 'Nova versão disponível. Atualize para usar a mais recente.'
            }
            className="fixed bottom-[calc(env(safe-area-inset-bottom)+1rem)] left-1/2 z-40 flex w-max max-w-[calc(100%-1.5rem)] -translate-x-1/2 animate-[nova-versao-subir_0.5s_cubic-bezier(0.16,0.84,0.3,1)_0.3s_both] items-center gap-2 rounded-full bg-foreground py-1 pr-1 pl-3 text-background shadow-[0_12px_32px_rgba(34,26,23,0.3)] motion-reduce:animate-none md:bottom-6 md:left-6 md:max-w-[calc(100%-3rem)] md:translate-x-0 md:gap-2.5 md:pl-3.5"
        >
            <Sparkles
                aria-hidden="true"
                className="size-4 shrink-0 text-dourado-claro"
            />
            <b className="font-semibold whitespace-nowrap">
                {atualizando ? (
                    'Atualizando…'
                ) : (
                    <>
                        Nova versão
                        <span className="hidden md:inline"> disponível</span>
                    </>
                )}
            </b>
            {!atualizando && (
                <button
                    type="button"
                    onClick={adiar}
                    aria-label="Depois"
                    className="flex min-h-9 items-center justify-center rounded-full px-2.5 font-semibold text-background/70 hover:text-background max-md:w-9 max-md:px-0"
                >
                    <X aria-hidden="true" className="size-4 md:hidden" />
                    <span className="hidden md:inline">Depois</span>
                </button>
            )}
            <button
                type="button"
                onClick={() => void atualizar()}
                disabled={atualizando}
                className="flex min-h-9 items-center gap-1.5 rounded-full bg-dourado-claro px-3.5 font-semibold text-foreground hover:bg-dourado-claro/90 disabled:opacity-100"
            >
                <RotateCw
                    aria-hidden="true"
                    className={cn(
                        'size-[15px]',
                        atualizando &&
                            'animate-spin motion-reduce:animate-[spin_2.4s_linear_infinite]',
                    )}
                />
                {atualizando ? 'Atualizando' : 'Atualizar'}
            </button>
        </section>
    );
}

/** Lembrete no topo (computador) depois de "Depois": reabre o aviso. */
export function SeloDeNovaVersao() {
    const { nova, adiado } = useNovaVersao();

    if (!nova || !adiado) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={reabrir}
            className="hidden min-h-10 items-center gap-2 rounded-full border border-aviso/25 bg-aviso-fundo px-3.5 font-semibold text-aviso md:flex"
        >
            <span
                aria-hidden="true"
                className="size-2 animate-pulse rounded-full bg-aviso motion-reduce:animate-none"
            />
            Nova versão
        </button>
    );
}
