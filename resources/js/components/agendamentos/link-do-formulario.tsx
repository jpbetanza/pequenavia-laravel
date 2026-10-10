import { toast } from 'sonner';
import { useRef } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = {
    /** Endereço absoluto do formulário público, vindo do servidor. */
    endereco: string;
};

/** Link do formulário público, com "Copiar" e "Ver". Sem clipboard, seleciona o texto para copiar à mão. */
export default function LinkDoFormulario({ endereco }: Props) {
    const campo = useRef<HTMLInputElement>(null);

    async function copiar() {
        try {
            if (!navigator.clipboard) {
                throw new Error('Clipboard indisponível');
            }

            await navigator.clipboard.writeText(endereco);
            toast.success('Link copiado.');
        } catch {
            campo.current?.focus();
            campo.current?.select();
            toast.error(
                'Não foi possível copiar. O link está selecionado: copie manualmente.',
            );
        }
    }

    return (
        <section
            aria-labelledby="link-do-formulario"
            className="flex w-full flex-col gap-2 rounded-2xl border bg-card p-4 lg:max-w-xl"
        >
            <Label
                id="link-do-formulario"
                htmlFor="link-do-formulario-campo"
                className="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                Link do formulário
            </Label>
            <div className="flex flex-col gap-2 sm:flex-row">
                <Input
                    id="link-do-formulario-campo"
                    ref={campo}
                    readOnly
                    value={endereco}
                    onFocus={(evento) => evento.currentTarget.select()}
                    className="h-11 min-w-0 flex-1 bg-muted/40 text-base"
                />
                <div className="flex gap-2">
                    <Button
                        type="button"
                        onClick={() => void copiar()}
                        className="h-11 flex-1 sm:flex-none"
                    >
                        Copiar
                    </Button>
                    <a
                        href={endereco}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={cn(
                            buttonVariants({ variant: 'outline' }),
                            'h-11 flex-1 sm:flex-none',
                        )}
                    >
                        Ver
                        <span className="sr-only"> (abre em nova aba)</span>
                    </a>
                </div>
            </div>
        </section>
    );
}
