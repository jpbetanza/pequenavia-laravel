import {
    agruparPorDia,
    escolherDestaque,
    horaDaGeracao,
    ordenarValores,
    valoresResumidos,
} from '@/lib/historico';
import { cn } from '@/lib/utils';
import type { Geracao } from '@/types/certificados';
import SeloDoModelo from './selo-do-modelo';

type Props = {
    geracoes: Geracao[];
    agora: Date;
    selecionadaId: string | null;
    aoSelecionar: (geracao: Geracao) => void;
};

export default function ListaDoHistorico({
    geracoes,
    agora,
    selecionadaId,
    aoSelecionar,
}: Props) {
    const grupos = agruparPorDia(geracoes, agora);

    return (
        <div className="flex flex-col gap-7">
            {grupos.map((grupo, indice) => (
                <section
                    key={grupo.titulo}
                    aria-labelledby={`dia-${indice}`}
                    className="flex flex-col gap-2.5"
                >
                    <h2
                        id={`dia-${indice}`}
                        className="flex flex-wrap items-baseline gap-3"
                    >
                        <span className="font-serif text-2xl font-semibold">
                            {grupo.titulo}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {grupo.itens.length === 1
                                ? '1 certificado'
                                : `${grupo.itens.length} certificados`}
                        </span>
                    </h2>
                    <ol className="overflow-hidden rounded-2xl border border-border bg-card">
                        {grupo.itens.map((geracao) => {
                            const { destaque, demais } = escolherDestaque(
                                ordenarValores(
                                    geracao.campos_valores,
                                    geracao.ordem_campos,
                                ),
                            );
                            const selecionada = geracao.id === selecionadaId;

                            return (
                                <li
                                    key={geracao.id}
                                    className="border-b border-border last:border-b-0"
                                >
                                    <button
                                        type="button"
                                        aria-pressed={selecionada}
                                        onClick={() => aoSelecionar(geracao)}
                                        className={cn(
                                            'flex min-h-11 w-full flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3.5 text-left transition-colors outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:px-5',
                                            selecionada && 'bg-accent',
                                        )}
                                    >
                                        <span className="w-12 shrink-0 text-sm text-muted-foreground tabular-nums">
                                            {horaDaGeracao(geracao.created_at)}
                                        </span>
                                        <span className="flex min-w-0 flex-1 basis-56 flex-col leading-tight">
                                            <span className="truncate font-serif text-[21px] font-semibold">
                                                {destaque?.[1] ??
                                                    geracao.modelo_title}
                                            </span>
                                            {demais.length > 0 && (
                                                <span className="truncate text-sm text-muted-foreground">
                                                    {valoresResumidos(demais)}
                                                </span>
                                            )}
                                        </span>
                                        <SeloDoModelo
                                            titulo={geracao.modelo_title}
                                        />
                                    </button>
                                </li>
                            );
                        })}
                    </ol>
                </section>
            ))}
        </div>
    );
}
