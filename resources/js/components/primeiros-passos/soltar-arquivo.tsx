import { useRef, useState, type DragEvent, type ReactNode } from 'react';
import { EXTENSOES_ACEITAS } from '@/lib/assistente/anexos';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    aoEscolher: (arquivos: File[]) => void;
    className?: string;
    /** Abre a câmera no celular (foto de uma lista). */
    camera?: boolean;
    children: ReactNode;
};

function trazArquivos(evento: DragEvent): boolean {
    return Array.from(evento.dataTransfer.types).includes('Files');
}

/**
 * Área para soltar ou escolher um arquivo que vai para o assistente (planilha, PDF, foto de uma lista).
 * É um `<label>` do input escondido: clicar abre o seletor, e o teclado chega pelo input.
 */
export function SoltarArquivo({
    id,
    aoEscolher,
    className,
    camera = false,
    children,
}: Props) {
    const [arrastando, setArrastando] = useState(false);
    const profundidade = useRef(0);

    return (
        <label
            htmlFor={id}
            onDragEnter={(evento) => {
                if (!trazArquivos(evento)) {
                    return;
                }

                evento.preventDefault();
                profundidade.current++;
                setArrastando(true);
            }}
            onDragOver={(evento) => {
                if (trazArquivos(evento)) {
                    evento.preventDefault();
                    evento.dataTransfer.dropEffect = 'copy';
                }
            }}
            onDragLeave={(evento) => {
                if (!trazArquivos(evento)) {
                    return;
                }

                profundidade.current = Math.max(0, profundidade.current - 1);

                if (profundidade.current === 0) {
                    setArrastando(false);
                }
            }}
            onDrop={(evento) => {
                if (!trazArquivos(evento)) {
                    return;
                }

                evento.preventDefault();
                profundidade.current = 0;
                setArrastando(false);
                const arquivos = Array.from(evento.dataTransfer.files);

                if (arquivos.length > 0) {
                    aoEscolher(arquivos);
                }
            }}
            className={cn(
                'cursor-pointer border-[1.5px] border-dashed transition-colors focus-within:ring-2 focus-within:ring-ring/50',
                arrastando && 'border-solid',
                className,
            )}
            data-arrastando={arrastando || undefined}
        >
            {children}
            <input
                id={id}
                type="file"
                multiple={!camera}
                accept={camera ? 'image/*' : EXTENSOES_ACEITAS}
                capture={camera ? 'environment' : undefined}
                className="sr-only"
                onChange={(evento) => {
                    const arquivos = Array.from(evento.target.files ?? []);

                    // Limpa para que escolher o mesmo arquivo de novo dispare o change.
                    evento.target.value = '';

                    if (arquivos.length > 0) {
                        aoEscolher(arquivos);
                    }
                }}
            />
        </label>
    );
}
