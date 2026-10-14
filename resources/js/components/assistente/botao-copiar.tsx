import { Check, Copy } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { useClipboard } from '@/hooks/use-clipboard';

/** "Copiar" discreto embaixo de uma resposta do assistente (ex.: a mensagem para o WhatsApp dos grupos). */
export function BotaoCopiar({ texto }: { texto: string }) {
    const [, copiar] = useClipboard();
    const [copiado, setCopiado] = useState(false);

    useEffect(() => {
        if (!copiado) {
            return;
        }

        const volta = window.setTimeout(() => setCopiado(false), 2000);

        return () => window.clearTimeout(volta);
    }, [copiado]);

    return (
        <button
            type="button"
            aria-label="Copiar resposta"
            onClick={async () => {
                if (await copiar(texto)) {
                    setCopiado(true);
                } else {
                    toast.error('Não consegui copiar.');
                }
            }}
            className="-my-2 -ml-2 flex min-h-11 items-center gap-1.5 self-start rounded-lg px-2 text-xs font-medium text-muted-foreground hover:text-foreground"
        >
            {copiado ? (
                <Check aria-hidden="true" className="size-3.5" />
            ) : (
                <Copy aria-hidden="true" className="size-3.5" />
            )}
            {copiado ? 'Copiado' : 'Copiar'}
        </button>
    );
}
