import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { index } from '@/routes/meus-certificados';

type Props = {
    titulo: string;
    subtitulo?: string;
    /** Título do modelo: mostra o caminho "← Meus certificados / {modelo}" à esquerda. */
    modelo?: string;
    acoes?: ReactNode;
};

export default function CabecalhoDaPagina({
    titulo,
    subtitulo,
    modelo,
    acoes,
}: Props) {
    return (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
            {modelo !== undefined && (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Link
                        href={index()}
                        className="flex items-center gap-1 transition-colors hover:text-foreground"
                    >
                        <ChevronLeft className="size-4" />
                        Meus certificados
                    </Link>
                    <span>/</span>
                    <span className="font-medium text-foreground">
                        {modelo}
                    </span>
                </div>
            )}
            <div className={modelo !== undefined ? 'text-right' : ''}>
                <h1 className="font-serif text-3xl font-bold">{titulo}</h1>
                {subtitulo && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {subtitulo}
                    </p>
                )}
            </div>
            {acoes}
        </div>
    );
}
