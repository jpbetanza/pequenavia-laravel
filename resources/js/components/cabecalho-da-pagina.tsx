import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { index } from '@/routes/meus-certificados';

type Props = {
    titulo: string;
    subtitulo?: string;
    /** Título do modelo: mostra "‹ Modelos" acima do título, com o nome do modelo ao lado. */
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
        <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div className="flex min-w-0 flex-col gap-2">
                {modelo !== undefined && (
                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <Link
                            href={index()}
                            className="flex min-h-11 items-center gap-1 font-medium text-primary transition-colors hover:text-foreground"
                        >
                            <ChevronLeft
                                className="size-4"
                                aria-hidden="true"
                            />
                            Modelos
                        </Link>
                        <span aria-hidden="true">/</span>
                        <span className="truncate text-foreground">
                            {modelo}
                        </span>
                    </div>
                )}
                <h1 className="font-serif text-5xl leading-none font-semibold">
                    {titulo}
                </h1>
                {subtitulo && (
                    <p className="text-muted-foreground">{subtitulo}</p>
                )}
            </div>
            {acoes && (
                <div className="flex flex-wrap items-center gap-2">{acoes}</div>
            )}
        </div>
    );
}
