import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/Agendamentos/GrupoController';
import { tratamentoDeErro } from '@/components/modelos/erro-de-acao';
import InputError from '@/components/input-error';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { GrupoResumo } from '@/types/agendamentos';

type Props = {
    /** Grupo sendo editado, ou null para criar um novo. */
    grupo: GrupoResumo | null;
    aoFechar: () => void;
};

type DadosDoGrupo = {
    nome: string;
    coordenacao: string;
    contato: string;
    observacao: string;
    ativo: boolean;
};

/**
 * Diálogo para criar ou editar um grupo. É montado só enquanto estiver aberto,
 * então cada abertura começa com o formulário limpo ou com os dados do grupo.
 */
export default function DialogoDeGrupo({ grupo, aoFechar }: Props) {
    const formulario = useForm<DadosDoGrupo>({
        nome: grupo?.nome ?? '',
        coordenacao: grupo?.coordenacao ?? '',
        contato: grupo?.contato ?? '',
        observacao: grupo?.observacao ?? '',
        ativo: grupo?.ativo ?? true,
    });
    const [confirmandoExclusao, setConfirmandoExclusao] = useState(false);
    const [excluindo, setExcluindo] = useState(false);

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();

        if (formulario.processing) {
            return;
        }

        const opcoes = {
            preserveScroll: true,
            onSuccess: aoFechar,
            onHttpException: tratamentoDeErro.onHttpException,
            onNetworkError: tratamentoDeErro.onNetworkError,
        };

        if (grupo) {
            formulario.put(update.url({ grupo: grupo.id }), opcoes);
        } else {
            formulario.post(store.url(), opcoes);
        }
    };

    const excluir = () => {
        if (!grupo || excluindo) {
            return;
        }

        router.delete(destroy.url({ grupo: grupo.id }), {
            preserveScroll: true,
            onStart: () => setExcluindo(true),
            onFinish: () => setExcluindo(false),
            onSuccess: () => {
                setConfirmandoExclusao(false);
                aoFechar();
            },
            ...tratamentoDeErro,
        });
    };

    return (
        <>
            <Dialog open onOpenChange={(abrir) => !abrir && aoFechar()}>
                <DialogContent>
                    <form onSubmit={enviar} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>
                                {grupo ? 'Editar grupo' : 'Novo grupo'}
                            </DialogTitle>
                            <DialogDescription>
                                Quem usa os espaços, com o contato de quem
                                coordena.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-2">
                            <Label htmlFor="nome-do-grupo">Nome</Label>
                            <Input
                                id="nome-do-grupo"
                                className="h-11"
                                placeholder="Ex.: Catequese"
                                maxLength={120}
                                value={formulario.data.nome}
                                onChange={(evento) =>
                                    formulario.setData(
                                        'nome',
                                        evento.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={formulario.errors.nome} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="coordenacao-do-grupo">
                                Coordenação
                            </Label>
                            <Input
                                id="coordenacao-do-grupo"
                                className="h-11"
                                placeholder="Ex.: Irmã Lúcia"
                                maxLength={120}
                                value={formulario.data.coordenacao}
                                onChange={(evento) =>
                                    formulario.setData(
                                        'coordenacao',
                                        evento.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={formulario.errors.coordenacao}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="contato-do-grupo">Contato</Label>
                            <Input
                                id="contato-do-grupo"
                                className="h-11"
                                placeholder="Telefone ou e-mail"
                                maxLength={120}
                                value={formulario.data.contato}
                                onChange={(evento) =>
                                    formulario.setData(
                                        'contato',
                                        evento.target.value,
                                    )
                                }
                            />
                            <InputError message={formulario.errors.contato} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="observacao-do-grupo">
                                Observação
                            </Label>
                            <Textarea
                                id="observacao-do-grupo"
                                placeholder="Opcional"
                                value={formulario.data.observacao}
                                onChange={(evento) =>
                                    formulario.setData(
                                        'observacao',
                                        evento.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={formulario.errors.observacao}
                            />
                        </div>

                        <Label
                            htmlFor="grupo-ativo"
                            className="flex min-h-11 cursor-pointer items-center justify-between gap-4 rounded-xl border border-input px-4 py-2"
                        >
                            <span className="flex flex-col">
                                <span className="font-medium">Ativo</span>
                                <span className="text-sm font-normal text-muted-foreground">
                                    Grupos inativos saem da lista do formulário
                                    de solicitação.
                                </span>
                            </span>
                            <Switch
                                id="grupo-ativo"
                                checked={formulario.data.ativo}
                                onCheckedChange={(ativo) =>
                                    formulario.setData('ativo', ativo)
                                }
                            />
                        </Label>
                        <InputError message={formulario.errors.ativo} />

                        <DialogFooter className="gap-2 sm:justify-between">
                            {grupo ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="min-h-11 text-destructive hover:text-destructive"
                                    onClick={() => setConfirmandoExclusao(true)}
                                    disabled={
                                        formulario.processing || excluindo
                                    }
                                >
                                    <Trash2 />
                                    Excluir grupo
                                </Button>
                            ) : (
                                <span />
                            )}
                            <div className="flex flex-col-reverse gap-2 sm:flex-row">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={aoFechar}
                                    disabled={formulario.processing}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    className="min-h-11"
                                    disabled={formulario.processing}
                                >
                                    {formulario.processing && <Spinner />}
                                    Salvar
                                </Button>
                            </div>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {grupo && (
                <AlertDialog
                    open={confirmandoExclusao}
                    onOpenChange={setConfirmandoExclusao}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Excluir "{grupo.nome}"?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Esta ação não pode ser desfeita. As reservas
                                deste grupo continuam na agenda, mas sem grupo.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={excluindo}>
                                Cancelar
                            </AlertDialogCancel>
                            <AlertDialogAction
                                disabled={excluindo}
                                onClick={(evento) => {
                                    evento.preventDefault();
                                    excluir();
                                }}
                            >
                                {excluindo && <Spinner />}
                                Excluir
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            )}
        </>
    );
}
