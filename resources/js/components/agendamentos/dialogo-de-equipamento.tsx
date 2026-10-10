import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/Agendamentos/EquipamentoController';
import InputError from '@/components/input-error';
import { tratamentoDeErro } from '@/components/modelos/erro-de-acao';
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
import type { EquipamentoResumo } from '@/types/agendamentos';

type Props = {
    /** Equipamento sendo editado, ou null para criar um novo. */
    equipamento: EquipamentoResumo | null;
    aoFechar: () => void;
};

type DadosDoEquipamento = {
    nome: string;
    descricao: string;
    quantidade: number;
    local_guarda: string;
    manutencao_ate: string;
};

/**
 * Diálogo para criar ou editar um equipamento. É montado só enquanto estiver aberto,
 * então cada abertura começa com o formulário limpo ou com os dados do equipamento.
 */
export default function DialogoDeEquipamento({ equipamento, aoFechar }: Props) {
    const formulario = useForm<DadosDoEquipamento>({
        nome: equipamento?.nome ?? '',
        descricao: equipamento?.descricao ?? '',
        quantidade: equipamento?.quantidade ?? 1,
        local_guarda: equipamento?.local_guarda ?? '',
        manutencao_ate: equipamento?.manutencao_ate ?? '',
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

        if (equipamento) {
            formulario.put(update.url({ equipamento: equipamento.id }), opcoes);
        } else {
            formulario.post(store.url(), opcoes);
        }
    };

    const excluir = () => {
        if (!equipamento || excluindo) {
            return;
        }

        router.delete(destroy.url({ equipamento: equipamento.id }), {
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
                                {equipamento
                                    ? 'Editar equipamento'
                                    : 'Novo equipamento'}
                            </DialogTitle>
                            <DialogDescription>
                                Itens reservados junto com a sala.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-2">
                            <Label htmlFor="nome-do-equipamento">Nome</Label>
                            <Input
                                id="nome-do-equipamento"
                                className="h-11"
                                placeholder="Ex.: Projetor"
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
                            <Label htmlFor="descricao-do-equipamento">
                                Descrição
                            </Label>
                            <Input
                                id="descricao-do-equipamento"
                                className="h-11"
                                placeholder="Ex.: com cabo HDMI e controle"
                                maxLength={160}
                                value={formulario.data.descricao}
                                onChange={(evento) =>
                                    formulario.setData(
                                        'descricao',
                                        evento.target.value,
                                    )
                                }
                            />
                            <InputError message={formulario.errors.descricao} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="quantidade-do-equipamento">
                                Unidades
                            </Label>
                            <Input
                                id="quantidade-do-equipamento"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                step={1}
                                className="h-11 w-32"
                                value={formulario.data.quantidade}
                                onChange={(evento) =>
                                    formulario.setData(
                                        'quantidade',
                                        Number(evento.target.value),
                                    )
                                }
                                required
                            />
                            <InputError
                                message={formulario.errors.quantidade}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="local-do-equipamento">
                                Onde fica guardado
                            </Label>
                            <Input
                                id="local-do-equipamento"
                                className="h-11"
                                placeholder="Ex.: Armário da secretaria"
                                maxLength={120}
                                value={formulario.data.local_guarda}
                                onChange={(evento) =>
                                    formulario.setData(
                                        'local_guarda',
                                        evento.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={formulario.errors.local_guarda}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="manutencao-do-equipamento">
                                Em manutenção até
                            </Label>
                            <Input
                                id="manutencao-do-equipamento"
                                type="date"
                                className="h-11 w-48"
                                value={formulario.data.manutencao_ate}
                                onChange={(evento) =>
                                    formulario.setData(
                                        'manutencao_ate',
                                        evento.target.value,
                                    )
                                }
                            />
                            <p className="text-sm text-muted-foreground">
                                Enquanto estiver em manutenção, o equipamento
                                não aparece para reservar.
                            </p>
                            <InputError
                                message={formulario.errors.manutencao_ate}
                            />
                        </div>

                        <DialogFooter className="gap-2 sm:justify-between">
                            {equipamento ? (
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
                                    Excluir equipamento
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

            {equipamento && (
                <AlertDialog
                    open={confirmandoExclusao}
                    onOpenChange={setConfirmandoExclusao}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Excluir "{equipamento.nome}"?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Esta ação não pode ser desfeita. Equipamentos
                                com reservas não podem ser excluídos.
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
