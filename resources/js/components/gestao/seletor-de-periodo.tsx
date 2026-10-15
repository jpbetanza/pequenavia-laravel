import { router, usePage } from '@inertiajs/react';
import type { PeriodoDaGestao } from '@/types/gestao';
import { cn } from '@/lib/utils';

const PERIODOS: PeriodoDaGestao[] = [7, 30, 90];

type Props = {
    periodo: PeriodoDaGestao;
};

/** Escolhe 7, 30 ou 90 dias mantendo os outros filtros da página. Volta à primeira página de listas. */
export function SeletorDePeriodo({ periodo }: Props) {
    const { url } = usePage();

    function escolher(dias: PeriodoDaGestao) {
        const [caminho, query = ''] = url.split('?');
        const atuais = Object.fromEntries(new URLSearchParams(query));
        const { page: _pagina, ...demais } = atuais;

        router.get(
            caminho,
            { ...demais, periodo: dias },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <div
            role="group"
            aria-label="Período"
            className="inline-flex gap-0.5 rounded-full border border-border bg-card p-0.5"
        >
            {PERIODOS.map((dias) => {
                const ativo = dias === periodo;

                return (
                    <button
                        key={dias}
                        type="button"
                        aria-pressed={ativo}
                        onClick={() => escolher(dias)}
                        className={cn(
                            'flex min-h-11 items-center rounded-full px-3 text-sm font-semibold transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring sm:min-h-8',
                            ativo
                                ? 'bg-foreground text-background'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {dias} dias
                    </button>
                );
            })}
        </div>
    );
}
