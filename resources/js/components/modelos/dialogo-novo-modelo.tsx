import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { store } from '@/actions/App/Http/Controllers/ModeloCertificadoController';
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

type Props = {
    aberto: boolean;
    aoFechar: () => void;
};

export default function DialogoNovoModelo({ aberto, aoFechar }: Props) {
    const formulario = useForm<{
        title: string;
        description: string;
        imagem: File | null;
    }>({
        title: '',
        description: '',
        imagem: null,
    });

    const fechar = () => {
        formulario.reset();
        formulario.clearErrors();
        aoFechar();
    };

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();
        formulario.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: fechar,
        });
    };

    return (
        <Dialog
            open={aberto}
            onOpenChange={(abrir) =>
                !abrir && !formulario.processing && fechar()
            }
        >
            <DialogContent>
                <form onSubmit={enviar} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Adicionar novo modelo</DialogTitle>
                        <DialogDescription>
                            Envie a imagem do certificado em branco (PNG, JPG ou
                            WEBP, até 50 MB).
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="titulo-do-modelo">Título</Label>
                        <Input
                            id="titulo-do-modelo"
                            placeholder="Nome do modelo"
                            value={formulario.data.title}
                            onChange={(evento) =>
                                formulario.setData('title', evento.target.value)
                            }
                            required
                        />
                        <InputError message={formulario.errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="descricao-do-modelo">Descrição</Label>
                        <Textarea
                            id="descricao-do-modelo"
                            placeholder="Descrição opcional"
                            value={formulario.data.description}
                            onChange={(evento) =>
                                formulario.setData(
                                    'description',
                                    evento.target.value,
                                )
                            }
                        />
                        <InputError message={formulario.errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="imagem-do-modelo">Imagem</Label>
                        <Input
                            id="imagem-do-modelo"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            onChange={(evento) =>
                                formulario.setData(
                                    'imagem',
                                    evento.target.files?.[0] ?? null,
                                )
                            }
                            required
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
                            onClick={fechar}
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
            </DialogContent>
        </Dialog>
    );
}
