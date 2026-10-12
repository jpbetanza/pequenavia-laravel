import { Clock } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';

/** Linha de andamento da resposta: "Consultando a agenda…". Com spinner enquanto a resposta chega. */
export function LinhaDeStatus({
    texto,
    pendente,
}: {
    texto: string;
    pendente: boolean;
}) {
    return (
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <Clock aria-hidden="true" className="size-3.5 shrink-0" />
            {texto}
            {pendente && <Spinner className="size-3" />}
        </p>
    );
}
