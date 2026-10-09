import { Download } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type Props = {
    aberto: boolean;
    /** Enquanto a planilha é lida o diálogo não pode fechar. */
    ocupado: boolean;
    aoFechar: () => void;
    aoEscolherArquivo: (arquivo: File) => void;
    aoBaixarModelo: () => void;
};

const EXTENSOES_ACEITAS = ['.xlsx', '.xls'];

export default function DialogoDeImportacao({
    aberto,
    ocupado,
    aoFechar,
    aoEscolherArquivo,
    aoBaixarModelo,
}: Props) {
    const [arrastando, setArrastando] = useState(false);
    const entrada = useRef<HTMLInputElement>(null);

    const aceitar = (arquivo: File | undefined) => {
        if (ocupado || !arquivo) {
            return;
        }

        if (
            EXTENSOES_ACEITAS.some((extensao) =>
                arquivo.name.toLowerCase().endsWith(extensao),
            )
        ) {
            aoEscolherArquivo(arquivo);
        } else {
            toast.error('Formato não aceito. Envie um arquivo .xlsx ou .xls.');
        }
    };

    return (
        <Dialog
            open={aberto}
            onOpenChange={(abrir) => {
                if (!abrir && !ocupado) {
                    aoFechar();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Importar dados</DialogTitle>
                    <DialogDescription>
                        Cada linha da planilha vira um certificado. As colunas
                        precisam ter o nome dos campos do modelo.
                    </DialogDescription>
                </DialogHeader>

                <div
                    className={cn(
                        'flex flex-col items-center gap-4 rounded-lg border-2 border-dashed p-10 transition-colors',
                        arrastando
                            ? 'border-primary bg-primary/10'
                            : 'border-border',
                    )}
                    onDragOver={(evento) => {
                        evento.preventDefault();
                        setArrastando(true);
                    }}
                    onDragLeave={(evento) => {
                        evento.preventDefault();
                        setArrastando(false);
                    }}
                    onDrop={(evento) => {
                        evento.preventDefault();
                        setArrastando(false);
                        aceitar(evento.dataTransfer.files[0]);
                    }}
                >
                    <p className="text-center text-muted-foreground">
                        {ocupado
                            ? 'Lendo a planilha…'
                            : arrastando
                              ? 'Solte o arquivo aqui'
                              : 'Arraste um arquivo Excel aqui ou clique para selecionar'}
                    </p>
                    <Button
                        type="button"
                        disabled={ocupado}
                        onClick={() => entrada.current?.click()}
                    >
                        Selecionar arquivo
                    </Button>
                    <input
                        ref={entrada}
                        type="file"
                        accept=".xlsx,.xls"
                        className="hidden"
                        aria-label="Selecionar planilha"
                        disabled={ocupado}
                        onChange={(evento) => {
                            aceitar(evento.target.files?.[0]);
                            evento.target.value = '';
                        }}
                    />
                    <p className="text-xs text-muted-foreground">
                        Formatos aceitos: .xlsx, .xls
                    </p>
                </div>

                <DialogFooter className="sm:justify-between">
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={ocupado}
                        onClick={aoBaixarModelo}
                    >
                        <Download />
                        Baixar planilha modelo
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={ocupado}
                        onClick={aoFechar}
                    >
                        Cancelar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
