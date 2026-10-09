import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/CompartilhamentoController';
import InputError from '@/components/input-error';
import { tratamentoDeErro } from '@/components/modelos/erro-de-acao';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    modelo: ModeloResumo;
};

export default function PainelDeCompartilhamento({ modelo }: Props) {
    const formulario = useForm({ email: '' });
    const [processando, setProcessando] = useState(false);
    const opcoes = { preserveScroll: true, preserveState: true } as const;
    const controlesDeAcao = {
        ...opcoes,
        onStart: () => setProcessando(true),
        onFinish: () => setProcessando(false),
        ...tratamentoDeErro,
    };

    const adicionar = (evento: FormEvent) => {
        evento.preventDefault();
        formulario.post(store.url({ modelo: modelo.id }), {
            ...opcoes,
            onSuccess: () => formulario.reset('email'),
        });
    };

    return (
        <div className="flex min-w-0 flex-1 flex-col gap-3">
            <div>
                <h3 className="text-sm font-semibold">Compartilhar modelo</h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Adicione e-mails para compartilhar este modelo.
                </p>
            </div>

            <form onSubmit={adicionar} className="grid gap-2">
                <div className="flex gap-2">
                    <Label
                        htmlFor="email-do-compartilhamento"
                        className="sr-only"
                    >
                        E-mail da pessoa
                    </Label>
                    <Input
                        id="email-do-compartilhamento"
                        type="email"
                        placeholder="E-mail do destinatário"
                        value={formulario.data.email}
                        onChange={(evento) =>
                            formulario.setData('email', evento.target.value)
                        }
                        className="flex-1"
                    />
                    <Button
                        type="submit"
                        size="icon"
                        aria-label="Adicionar e-mail"
                        disabled={
                            formulario.processing ||
                            formulario.data.email.trim() === ''
                        }
                    >
                        {formulario.processing ? <Spinner /> : <Plus />}
                    </Button>
                </div>
                <InputError message={formulario.errors.email} />
            </form>

            {modelo.compartilhamentos.length === 0 ? (
                <p className="py-4 text-center text-xs text-muted-foreground">
                    Nenhum compartilhamento ainda
                </p>
            ) : (
                <ul className="flex max-h-52 flex-col gap-2 overflow-y-auto">
                    {modelo.compartilhamentos.map((compartilhamento) => (
                        <li
                            key={compartilhamento.id}
                            className="flex items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2"
                        >
                            <span className="flex-1 truncate text-xs">
                                {compartilhamento.email}
                            </span>
                            <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                Pode editar
                                <Switch
                                    checked={compartilhamento.pode_editar}
                                    disabled={processando}
                                    onCheckedChange={(marcado) =>
                                        router.patch(
                                            update.url({
                                                modelo: modelo.id,
                                                compartilhamento:
                                                    compartilhamento.id,
                                            }),
                                            { pode_editar: marcado },
                                            controlesDeAcao,
                                        )
                                    }
                                />
                            </label>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 text-destructive"
                                aria-label={`Remover ${compartilhamento.email}`}
                                disabled={processando}
                                onClick={() =>
                                    router.delete(
                                        destroy.url({
                                            modelo: modelo.id,
                                            compartilhamento:
                                                compartilhamento.id,
                                        }),
                                        controlesDeAcao,
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
