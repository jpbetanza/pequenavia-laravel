import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatarData, ordenarValores } from '@/lib/historico';
import type { Geracao } from '@/types/certificados';

type Props = {
    geracao: Geracao | null;
    aoFechar: () => void;
};

export default function DialogoDeDetalhes({ geracao, aoFechar }: Props) {
    return (
        <Dialog
            open={geracao !== null}
            onOpenChange={(abrir) => !abrir && aoFechar()}
        >
            <DialogContent>
                {geracao && (
                    <>
                        <DialogHeader>
                            <DialogTitle>{geracao.modelo_title}</DialogTitle>
                            <DialogDescription>
                                {formatarData(geracao.created_at)}
                            </DialogDescription>
                        </DialogHeader>
                        <dl className="flex max-h-[60vh] flex-col gap-3 overflow-y-auto">
                            {ordenarValores(
                                geracao.campos_valores,
                                geracao.ordem_campos,
                            ).map(([nome, valor]) => (
                                <div
                                    key={nome}
                                    className="flex flex-col gap-0.5"
                                >
                                    <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        {nome}
                                    </dt>
                                    <dd className="text-sm">{valor}</dd>
                                </div>
                            ))}
                        </dl>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
