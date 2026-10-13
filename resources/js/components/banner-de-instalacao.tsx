import { Share, X } from 'lucide-react';
import { useEffect, useState, useSyncExternalStore } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useIsMobile } from '@/hooks/use-mobile';
import {
    assinarInstalacao,
    dispensadoRecentemente,
    dispensar,
    ehSafariDoIos,
    foiInstalado,
    instalar,
    pedidoDeInstalacao,
    rodandoComoAplicativo,
} from '@/lib/pwa/instalacao';

type Ambiente = { aplicativo: boolean; ios: boolean; dispensado: boolean };

/**
 * No celular, oferece instalar o PequenaVia como aplicativo. Android e Chrome: botão "Instalar", que abre o pedido
 * do sistema. iPhone: o passo a passo do Safari. Some quando já está instalado ou depois de "Agora não" (30 dias).
 */
export function BannerDeInstalacao() {
    const celular = useIsMobile();
    const pedido = useSyncExternalStore(
        assinarInstalacao,
        pedidoDeInstalacao,
        () => null,
    );
    const instalado = useSyncExternalStore(
        assinarInstalacao,
        foiInstalado,
        () => false,
    );
    // Lido só no navegador, depois de montar: o HTML do servidor não sabe o aparelho.
    const [ambiente, setAmbiente] = useState<Ambiente | null>(null);

    useEffect(() => {
        setAmbiente({
            aplicativo: rodandoComoAplicativo(),
            ios: ehSafariDoIos(navigator.userAgent, navigator.maxTouchPoints),
            dispensado: dispensadoRecentemente(),
        });
    }, []);

    if (
        !celular ||
        ambiente === null ||
        ambiente.aplicativo ||
        ambiente.dispensado ||
        instalado ||
        (pedido === null && !ambiente.ios)
    ) {
        return null;
    }

    function fechar() {
        dispensar();
        setAmbiente((atual) => atual && { ...atual, dispensado: true });
    }

    async function aoInstalar() {
        if (await instalar()) {
            toast.success('Pronto! O PequenaVia está na sua tela inicial.');
        }
    }

    return (
        <section
            aria-label="Instalar o aplicativo"
            className="fixed inset-x-3 bottom-[calc(env(safe-area-inset-bottom)+0.75rem)] z-40 flex animate-in items-start gap-3 rounded-2xl border bg-card p-3 shadow-lg duration-300 fade-in slide-in-from-bottom-4 motion-reduce:animate-none"
        >
            <img
                src="/icons/icon-192.png"
                alt=""
                className="size-12 shrink-0 rounded-xl border"
            />
            <div className="flex min-w-0 flex-1 flex-col gap-0.5 pt-0.5">
                <p className="font-semibold">Instale o PequenaVia</p>
                {pedido !== null ? (
                    <p className="text-sm text-muted-foreground">
                        Abra direto da tela inicial, como um aplicativo.
                    </p>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No Safari, toque em{' '}
                        <Share
                            aria-hidden="true"
                            className="inline size-4 align-text-bottom text-primary"
                        />{' '}
                        <span className="font-medium text-foreground">
                            Compartilhar
                        </span>{' '}
                        e depois em{' '}
                        <span className="font-medium text-foreground">
                            Adicionar à Tela de Início
                        </span>
                        .
                    </p>
                )}
                {pedido !== null && (
                    <Button
                        type="button"
                        onClick={() => void aoInstalar()}
                        className="mt-2 min-h-11 self-start"
                    >
                        Instalar
                    </Button>
                )}
            </div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Agora não"
                onClick={fechar}
                className="-mt-1 -mr-1 size-11 shrink-0 text-muted-foreground"
            >
                <X aria-hidden="true" />
            </Button>
        </section>
    );
}
