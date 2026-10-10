import { useState } from 'react';
import PreviewDoModelo from '@/components/campos/preview-do-modelo';
import type { LinhaDeGeracao } from '@/lib/geracao';
import type { Campo, ModeloBasico } from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    campos: Campo[];
    linha: LinhaDeGeracao | null;
    posicao: number;
};

/** Prévia ao vivo da linha selecionada, na coluna da direita (desktop). */
export default function PreviaDaLinha({
    modelo,
    campos,
    linha,
    posicao,
}: Props) {
    const [tamanho, setTamanho] = useState<{ w: number; h: number } | null>(
        null,
    );

    return (
        <section
            aria-label={`Pré-visualização da linha ${posicao}`}
            className="flex min-w-0 flex-col gap-3"
        >
            <div className="flex items-baseline justify-between gap-2">
                <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                    {linha ? `Linha ${posicao} · prévia` : 'Prévia'}
                </h2>
                <span className="text-sm text-muted-foreground">
                    atualiza enquanto você digita
                </span>
            </div>
            <PreviewDoModelo
                modelo={modelo}
                campos={campos}
                somenteLeitura
                arrastando={null}
                aoIniciarArraste={() => undefined}
                valores={linha?.valores}
                aoCarregar={setTamanho}
            />
            {tamanho && (
                <p className="text-sm text-muted-foreground">
                    O arquivo final sai no tamanho original da arte ({tamanho.w}{' '}
                    × {tamanho.h} px).
                </p>
            )}
        </section>
    );
}
