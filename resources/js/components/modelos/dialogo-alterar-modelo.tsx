import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { update } from '@/actions/App/Http/Controllers/ModeloCertificadoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    modelo: ModeloResumo | null;
    aoFechar: () => void;
};

function Formulario({
    modelo,
    aoFechar,
}: {
    modelo: ModeloResumo;
    aoFechar: () => void;
}) {
    const formulario = useForm<{
        title: string;
        description: string;
        imagem: File | null;
    }>({
        title: modelo.title,
        description: modelo.description ?? '',
        imagem: null,
    });

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();
        formulario.put(update.url({ modelo: modelo.id }), {
            forceFormData: formulario.data.imagem !== null,
            preserveScroll: true,
            onSuccess: aoFechar,
        });
    };

    return (
        <form onSubmit={enviar} className="space-y-4">
            <DialogHeader>
                <DialogTitle>Alterar modelo</DialogTitle>
                <DialogDescription>
                    Mude o título, a descrição ou troque a imagem do modelo.
                </DialogDescription>
            </DialogHeader>

            <img
                src={modelo.imagem_url}
                alt={modelo.title}
                className="aspect-[4/3] w-full rounded-md border object-cover"
            />

            <div className="grid gap-2">
                <Label htmlFor="alterar-titulo">Título</Label>
                <Input
                    id="alterar-titulo"
                    value={formulario.data.title}
                    onChange={(evento) =>
                        formulario.setData('title', evento.target.value)
                    }
                    required
                />
                <InputError message={formulario.errors.title} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="alterar-descricao">Descrição</Label>
                <Textarea
                    id="alterar-descricao"
                    value={formulario.data.description}
                    onChange={(evento) =>
                        formulario.setData('description', evento.target.value)
                    }
                />
                <InputError message={formulario.errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="alterar-imagem">Nova imagem (opcional)</Label>
                <Input
                    id="alterar-imagem"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    onChange={(evento) =>
                        formulario.setData(
                            'imagem',
                            evento.target.files?.[0] ?? null,
                        )
                    }
                />
                <InputError message={formulario.errors.imagem} />
                {formulario.progress && (
                    <progress
                        value={formulario.progress.percentage}
                        max="100"
                        className="h-2 w-full"
                    />
                )}
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={aoFechar}
                    disabled={formulario.processing}
                >
                    Cancelar
                </Button>
                <Button type="submit" disabled={formulario.processing}>
                    {formulario.processing && <Spinner />}
                    Salvar
                </Button>
            </DialogFooter>
        </form>
    );
}

export default function DialogoAlterarModelo({ modelo, aoFechar }: Props) {
    return (
        <Dialog
            open={modelo !== null}
            onOpenChange={(abrir) => !abrir && aoFechar()}
        >
            <DialogContent>
                {modelo && (
                    <Formulario
                        key={modelo.id}
                        modelo={modelo}
                        aoFechar={aoFechar}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
