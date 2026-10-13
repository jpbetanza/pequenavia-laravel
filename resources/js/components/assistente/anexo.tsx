import {
    FileImage,
    FileSpreadsheet,
    FileText,
    FileType,
    X,
} from 'lucide-react';
import { formatarTamanho } from '@/lib/assistente/anexos';
import { cn } from '@/lib/utils';
import type { AnexoDaMensagem, TipoDeAnexo } from '@/types/assistente';

const ICONES: Record<TipoDeAnexo, typeof FileText> = {
    planilha: FileSpreadsheet,
    texto: FileText,
    imagem: FileImage,
    pdf: FileType,
};

const ROTULOS: Record<TipoDeAnexo, string> = {
    planilha: 'Planilha',
    texto: 'Texto',
    imagem: 'Imagem',
    pdf: 'PDF',
};

type Props = {
    anexo: AnexoDaMensagem;
    /** Na bolha da mensagem enviada o chip fica sobre o vinho; no campo, sobre o fundo claro. */
    variante?: 'campo' | 'mensagem';
    /** Só no campo, antes de enviar. */
    aoRemover?: () => void;
};

/** Um arquivo anexado: ícone do tipo, nome, tamanho e, no campo, o botão de remover. */
export function Anexo({ anexo, variante = 'campo', aoRemover }: Props) {
    const Icone = ICONES[anexo.tipo];
    const detalhe = [
        ROTULOS[anexo.tipo],
        formatarTamanho(anexo.tamanho),
        ...(anexo.truncado ? ['só o começo'] : []),
    ].join(' · ');

    return (
        <div
            className={cn(
                'flex max-w-full min-w-0 items-center gap-2 rounded-xl py-1 pl-2 text-sm',
                aoRemover ? 'pr-0.5' : 'pr-3',
                variante === 'campo'
                    ? 'border bg-muted/40 text-foreground'
                    : 'bg-primary-foreground/12 text-primary-foreground',
            )}
        >
            <span
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                    variante === 'campo'
                        ? 'bg-background text-primary'
                        : 'bg-primary-foreground/15',
                )}
            >
                <Icone aria-hidden="true" className="size-4" />
            </span>
            <span className="flex min-w-0 flex-col leading-tight">
                <span className="truncate font-medium">{anexo.nome}</span>
                <span
                    className={cn(
                        'truncate text-xs',
                        variante === 'campo'
                            ? 'text-muted-foreground'
                            : 'text-primary-foreground/80',
                    )}
                >
                    {detalhe}
                </span>
            </span>
            {aoRemover && (
                <button
                    type="button"
                    onClick={aoRemover}
                    aria-label={`Remover ${anexo.nome}`}
                    className="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <X aria-hidden="true" className="size-4" />
                </button>
            )}
        </div>
    );
}
