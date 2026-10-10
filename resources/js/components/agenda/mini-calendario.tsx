import { ChevronLeft, ChevronRight } from 'lucide-react';
import {
    dataPorExtenso,
    deslocarMes,
    gradeDoMes,
    rotuloDoMes,
} from '@/lib/agenda/datas';
import { cn } from '@/lib/utils';

type Props = {
    /** Qualquer dia do mês exibido, "YYYY-MM-DD". */
    mes: string;
    /** Dia selecionado na página, "YYYY-MM-DD". */
    selecionado: string;
    hoje: string;
    onMudarMes: (mes: string) => void;
    /** Clique num dia: a página navega para ele. */
    onSelecionarDia: (dia: string) => void;
};

const INICIAIS_DOS_DIAS = ['S', 'T', 'Q', 'Q', 'S', 'S', 'D'];

export default function MiniCalendario({
    mes,
    selecionado,
    hoje,
    onMudarMes,
    onSelecionarDia,
}: Props) {
    const semanas = gradeDoMes(mes);
    const mesDoCabecalho = mes.slice(0, 7);
    const semanaSelecionada = semanas.find((semana) =>
        semana.includes(selecionado),
    );

    return (
        <section
            aria-label={rotuloDoMes(mes)}
            className="rounded-2xl border bg-card p-3.5"
        >
            <div className="mb-2 flex items-center justify-between">
                <span className="font-semibold">{rotuloDoMes(mes)}</span>
                <div className="flex gap-1">
                    <button
                        type="button"
                        aria-label="Mês anterior"
                        onClick={() => onMudarMes(deslocarMes(mes, -1))}
                        className="flex size-11 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:size-8"
                    >
                        <ChevronLeft className="size-4" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        aria-label="Próximo mês"
                        onClick={() => onMudarMes(deslocarMes(mes, 1))}
                        className="flex size-11 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:size-8"
                    >
                        <ChevronRight className="size-4" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div className="grid grid-cols-7 gap-0.5 text-center text-xs">
                {INICIAIS_DOS_DIAS.map((inicial, indice) => (
                    <span
                        key={`${inicial}-${indice}`}
                        aria-hidden="true"
                        className="py-1 text-muted-foreground"
                    >
                        {inicial}
                    </span>
                ))}

                {semanas.flatMap((semana) =>
                    semana.map((dia) => {
                        const deOutroMes = dia.slice(0, 7) !== mesDoCabecalho;
                        const ehSelecionado = dia === selecionado;
                        const naSemanaSelecionada =
                            semana === semanaSelecionada;

                        return (
                            <button
                                key={dia}
                                type="button"
                                aria-label={
                                    dia === hoje
                                        ? `${dataPorExtenso(dia)}, hoje`
                                        : dataPorExtenso(dia)
                                }
                                aria-current={
                                    ehSelecionado ? 'date' : undefined
                                }
                                onClick={() => onSelecionarDia(dia)}
                                className={cn(
                                    'flex h-11 items-center justify-center rounded-md text-sm transition-colors hover:bg-muted sm:h-8',
                                    deOutroMes && 'text-muted-foreground/60',
                                    naSemanaSelecionada &&
                                        !ehSelecionado &&
                                        'rounded-none bg-primary/10',
                                    ehSelecionado &&
                                        'bg-primary font-semibold text-primary-foreground hover:bg-primary/90',
                                    dia === hoje &&
                                        !ehSelecionado &&
                                        'font-bold text-primary',
                                )}
                            >
                                {Number(dia.slice(8))}
                            </button>
                        );
                    }),
                )}
            </div>
        </section>
    );
}
