import { Checkbox } from '@/components/ui/checkbox';
import { corDoEspaco, type CorDoEspaco } from '@/lib/agenda/cores';

export type EspacoDoFiltro = {
    id: string;
    nome: string;
    ativo: boolean;
    cor: CorDoEspaco;
};

type Props = {
    espacos: EspacoDoFiltro[];
    selecionados: string[];
    /** Recebe os ids marcados, na mesma ordem da lista de espaços. */
    onAlterar: (selecionados: string[]) => void;
};

export default function FiltroDeEspacos({
    espacos,
    selecionados,
    onAlterar,
}: Props) {
    const marcados = new Set(selecionados);

    const alternar = (id: string, marcado: boolean) => {
        onAlterar(
            espacos
                .filter((espaco) =>
                    espaco.id === id ? marcado : marcados.has(espaco.id),
                )
                .map((espaco) => espaco.id),
        );
    };

    return (
        <fieldset className="flex flex-col gap-1 rounded-2xl border bg-card p-3.5">
            <legend className="px-1 text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                Espaços
            </legend>

            {espacos.length === 0 && (
                <p className="px-1 py-2 text-sm text-muted-foreground">
                    Nenhum espaço ativo.
                </p>
            )}

            {espacos.map((espaco) => {
                const id = `filtro-espaco-${espaco.id}`;

                return (
                    <div
                        key={espaco.id}
                        className="flex min-h-11 items-center gap-3 sm:min-h-9"
                    >
                        <Checkbox
                            id={id}
                            checked={marcados.has(espaco.id)}
                            onCheckedChange={(valor) =>
                                alternar(espaco.id, valor === true)
                            }
                        />
                        <span
                            aria-hidden="true"
                            className="size-2.5 shrink-0 rounded-[3px]"
                            style={{
                                backgroundColor: corDoEspaco(espaco.cor).forte,
                            }}
                        />
                        <label
                            htmlFor={id}
                            className="min-w-0 flex-1 cursor-pointer truncate text-sm"
                        >
                            {espaco.nome}
                            {!espaco.ativo && ' (inativo)'}
                        </label>
                    </div>
                );
            })}
        </fieldset>
    );
}
