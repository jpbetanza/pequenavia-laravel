import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    titulo: string;
    /** Número grande; pode trazer um complemento menor ao lado. */
    valor: ReactNode;
    /** Linha de apoio, abaixo do número. */
    apoio?: ReactNode;
    tom?: 'neutro' | 'bom' | 'atencao';
    className?: string;
};

const CORES_DO_APOIO = {
    neutro: 'text-muted-foreground',
    bom: 'text-sucesso',
    atencao: 'text-aviso',
} as const;

/** Um número de destaque do painel: rótulo, valor grande em serifa e uma linha de apoio. */
export function CartaoNumero({
    titulo,
    valor,
    apoio,
    tom = 'neutro',
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex min-w-0 flex-col gap-1 rounded-2xl border border-border bg-card p-4',
                className,
            )}
        >
            <span className="text-sm text-muted-foreground">{titulo}</span>
            <span className="font-serif text-3xl leading-none font-semibold tabular-nums sm:text-4xl">
                {valor}
            </span>
            {apoio !== undefined && (
                <span
                    className={cn(
                        'text-xs leading-snug font-medium',
                        CORES_DO_APOIO[tom],
                    )}
                >
                    {apoio}
                </span>
            )}
        </div>
    );
}
