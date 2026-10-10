import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

const LIMITE_DO_MOTIVO = 200;

type Props = {
    enviando: boolean;
    aoFechar: () => void;
    aoConfirmar: (motivo: string) => void;
};

/** Diálogo de recusa, com motivo opcional de até 200 caracteres. Só fica montado enquanto está aberto. */
export default function DialogoDeRecusa({
    enviando,
    aoFechar,
    aoConfirmar,
}: Props) {
    const [motivo, setMotivo] = useState('');

    return (
        <Dialog
            open
            onOpenChange={(aberto) => {
                if (!aberto && !enviando) {
                    aoFechar();
                }
            }}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Recusar esta solicitação?</DialogTitle>
                    <DialogDescription>
                        O pedido sai da lista de pendentes e vai para Recusadas.
                        O motivo é opcional e aparece só para a secretaria.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="motivo-da-recusa">
                        Motivo{' '}
                        <span className="font-normal text-muted-foreground">
                            (opcional)
                        </span>
                    </Label>
                    <Textarea
                        id="motivo-da-recusa"
                        rows={3}
                        maxLength={LIMITE_DO_MOTIVO}
                        value={motivo}
                        aria-describedby="contador-do-motivo"
                        onChange={(evento) => setMotivo(evento.target.value)}
                    />
                    <p
                        id="contador-do-motivo"
                        className="text-right text-xs text-muted-foreground"
                    >
                        {motivo.length}/{LIMITE_DO_MOTIVO}
                    </p>
                </div>

                <DialogFooter className="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={aoFechar}
                        disabled={enviando}
                        className="h-11"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={enviando}
                        aria-busy={enviando}
                        onClick={() => aoConfirmar(motivo.trim())}
                        className="h-11"
                    >
                        {enviando && <Spinner />}
                        Recusar pedido
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
