import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Campo } from '@/types/certificados';

type Props = {
    campos: Campo[];
    selecionadoId: string | null;
    somenteLeitura: boolean;
    criando: boolean;
    aoSelecionar: (id: string) => void;
    aoCriar: () => void;
};

export default function ListaDeCampos({
    campos,
    selecionadoId,
    somenteLeitura,
    criando,
    aoSelecionar,
    aoCriar,
}: Props) {
    return (
        <aside className="flex flex-col gap-4">
            <div className="flex items-center justify-between gap-2">
                <h2 className="text-sm font-semibold">
                    Campos · {campos.length}
                </h2>
                {!somenteLeitura && (
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-11"
                        aria-label="Adicionar campo"
                        disabled={criando}
                        onClick={aoCriar}
                    >
                        <Plus />
                    </Button>
                )}
            </div>

            <ul className="flex flex-col gap-2">
                {campos.map((campo) => {
                    const ativo = campo.id === selecionadoId;

                    return (
                        <li key={campo.id}>
                            <button
                                type="button"
                                aria-current={ativo ? 'true' : undefined}
                                onClick={() => aoSelecionar(campo.id)}
                                className={cn(
                                    'flex min-h-11 w-full items-center gap-3 rounded-lg border p-3 text-left transition-colors',
                                    ativo
                                        ? 'border-primary bg-card'
                                        : 'border-transparent hover:bg-card',
                                )}
                            >
                                <span
                                    aria-hidden="true"
                                    className="flex size-10 shrink-0 items-center justify-center rounded-md bg-mesa text-lg"
                                    style={{
                                        fontFamily: `"${campo.fonte_familia}"`,
                                    }}
                                >
                                    Aa
                                </span>
                                <span className="flex min-w-0 flex-col">
                                    <span className="truncate text-sm font-medium">
                                        {campo.nome}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {campo.fonte_familia} ·{' '}
                                        {campo.centralizado
                                            ? 'centralizado'
                                            : `x ${Math.round(campo.x)}`}
                                    </span>
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ul>

            {campos.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Este modelo ainda não tem campos.
                </p>
            )}

            {!somenteLeitura && (
                <p className="text-xs text-muted-foreground">
                    Arraste o texto na imagem ou use as setas do teclado para
                    mover o campo selecionado.
                </p>
            )}
        </aside>
    );
}
