import { formatarNumero, formatarPct } from '@/lib/gestao/formatar';
import type { CasoDoPainel } from '@/types/gestao';

type Props = {
    casos: CasoDoPainel[];
};

/** Casos de uso do período, em barras: a barra mais longa é o caso mais frequente. */
export function BarraDeCasos({ casos }: Props) {
    if (casos.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nenhuma conversa classificada no período.
            </p>
        );
    }

    const maior = Math.max(1, ...casos.map((caso) => caso.pct ?? 0));

    return (
        <ol className="flex flex-col gap-3">
            {casos.map((caso) => (
                <li key={caso.caso} className="flex flex-col gap-1.5">
                    <span className="flex items-baseline gap-3 text-sm">
                        <span className="min-w-0 flex-1">{caso.rotulo}</span>
                        <span className="font-semibold tabular-nums">
                            {formatarPct(caso.pct)}
                        </span>
                    </span>
                    <span
                        aria-hidden="true"
                        className="block h-2 overflow-hidden rounded-full bg-secondary"
                    >
                        <span
                            className="block h-full rounded-full bg-primary"
                            style={{
                                width: `${((caso.pct ?? 0) / maior) * 100}%`,
                            }}
                        />
                    </span>
                    <span className="sr-only">
                        {formatarNumero(caso.total)} conversas
                    </span>
                </li>
            ))}
        </ol>
    );
}
