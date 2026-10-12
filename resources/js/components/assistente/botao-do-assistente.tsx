import { MessageSquareText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAssistente } from '@/hooks/use-assistente';
import { cn } from '@/lib/utils';

/** Abre e fecha o painel do assistente. Fica na barra superior, à direita. */
export function BotaoDoAssistente() {
    const { aberto, alternar } = useAssistente();

    return (
        <Button
            type="button"
            variant="outline"
            aria-pressed={aberto}
            aria-controls="painel-assistente"
            onClick={alternar}
            className={cn(
                'ml-auto min-h-11 rounded-full px-4',
                aberto && 'border-primary bg-primary/10 text-primary',
            )}
        >
            <MessageSquareText aria-hidden="true" />
            Assistente
        </Button>
    );
}
