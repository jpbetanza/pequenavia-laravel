import { MessageSquareText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAssistente } from '@/hooks/use-assistente';
import { cn } from '@/lib/utils';

/** Abre e fecha o painel do assistente. Fica na barra superior; a barra é quem o posiciona. */
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
                'hidden min-h-11 rounded-full px-4 lg:inline-flex',
                aberto && 'border-primary bg-primary/10 text-primary',
            )}
        >
            <MessageSquareText aria-hidden="true" />
            Assistente
        </Button>
    );
}
