import { router } from '@inertiajs/react';
import { FileText, Pencil, Settings, Share2, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/ModeloCertificadoController';
import { tratamentoDeErro } from '@/components/modelos/erro-de-acao';
import PainelDeCompartilhamento from '@/components/modelos/painel-de-compartilhamento';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { campos, gerar } from '@/routes/meus-certificados';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    modelo: ModeloResumo | null;
    aoFechar: () => void;
    aoAlterar: (id: string) => void;
};

type PropsDoConteudo = {
    modelo: ModeloResumo;
    compartilhando: boolean;
    aoAlternarCompartilhamento: () => void;
    aoFechar: () => void;
    aoAlterar: (id: string) => void;
};

function Conteudo({
    modelo,
    compartilhando,
    aoAlternarCompartilhamento,
    aoFechar,
    aoAlterar,
}: PropsDoConteudo) {
    const [confirmandoExclusao, setConfirmandoExclusao] = useState(false);
    const [excluindo, setExcluindo] = useState(false);
    const semCampos = modelo.campos_count === 0;

    const ir = (url: string) => {
        aoFechar();
        router.visit(url);
    };

    const excluir = () => {
        router.delete(destroy.url({ modelo: modelo.id }), {
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
            <DialogHeader>
                <DialogTitle>{modelo.title}</DialogTitle>
                <DialogDescription className="sr-only">
                    Opções do modelo: gerar certificados, configurar campos,
                    compartilhar, alterar ou excluir.
                </DialogDescription>
            </DialogHeader>

            <div className={cn('flex', compartilhando && 'gap-6')}>
                <div
                    className={cn(
                        'flex flex-col gap-2',
                        compartilhando ? 'w-[260px] shrink-0' : 'w-full',
                    )}
                >
                    {semCampos && (
                        <Alert>
                            <AlertDescription>
                                {modelo.pode_editar
                                    ? 'Este modelo não tem campos configurados. Configure os campos antes de gerar certificados.'
                                    : 'Este modelo ainda não tem campos configurados.'}
                            </AlertDescription>
                        </Alert>
                    )}

                    <Button
                        disabled={semCampos || excluindo}
                        onClick={() => ir(gerar.url({ modelo: modelo.id }))}
                    >
                        <FileText />
                        Gerar certificados
                    </Button>

                    <Button
                        variant={semCampos ? 'default' : 'secondary'}
                        onClick={() => ir(campos.url({ modelo: modelo.id }))}
                    >
                        <Settings />
                        {modelo.pode_editar
                            ? 'Configurar campos'
                            : 'Ver campos'}
                    </Button>

                    {modelo.eh_dono && (
                        <Button
                            variant={compartilhando ? 'default' : 'secondary'}
                            aria-pressed={compartilhando}
                            onClick={aoAlternarCompartilhamento}
                        >
                            <Share2 />
                            Compartilhar
                        </Button>
                    )}

                    <div className="flex gap-2">
                        {modelo.pode_editar && (
                            <Button
                                variant="secondary"
                                className="flex-1"
                                disabled={excluindo}
                                onClick={() => {
                                    aoFechar();
                                    aoAlterar(modelo.id);
                                }}
                            >
                                <Pencil />
                                Alterar
                            </Button>
                        )}
                        {modelo.eh_dono && (
                            <Button
                                variant="destructive"
                                className="flex-1"
                                disabled={excluindo}
                                onClick={() => setConfirmandoExclusao(true)}
                            >
                                <Trash2 />
                                Excluir
                            </Button>
                        )}
                    </div>
                </div>

                {compartilhando && modelo.eh_dono && (
                    <PainelDeCompartilhamento modelo={modelo} />
                )}
            </div>

            <AlertDialog
                open={confirmandoExclusao}
                onOpenChange={setConfirmandoExclusao}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Excluir "{modelo.title}"?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Esta ação não pode ser desfeita. O modelo, os campos
                            e a imagem serão apagados, e quem recebeu o
                            compartilhamento perde o acesso.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={excluindo}
                            onClick={(evento) => {
                                evento.preventDefault();
                                excluir();
                            }}
                        >
                            Excluir
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

export default function DialogoOpcoesDoModelo({
    modelo,
    aoFechar,
    aoAlterar,
}: Props) {
    const [compartilhando, setCompartilhando] = useState(false);

    const fechar = () => {
        setCompartilhando(false);
        aoFechar();
    };

    return (
        <Dialog
            open={modelo !== null}
            onOpenChange={(abrir) => !abrir && fechar()}
        >
            <DialogContent
                className={cn(
                    'transition-[max-width] duration-300',
                    compartilhando ? 'sm:max-w-3xl' : 'sm:max-w-sm',
                )}
            >
                {modelo && (
                    <Conteudo
                        key={modelo.id}
                        modelo={modelo}
                        compartilhando={compartilhando}
                        aoAlternarCompartilhamento={() =>
                            setCompartilhando((atual) => !atual)
                        }
                        aoFechar={fechar}
                        aoAlterar={aoAlterar}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
