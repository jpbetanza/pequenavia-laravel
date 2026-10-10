import { useEffect, useId, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

/** Cores da marca que aparecem como atalho ao lado do seletor livre. */
export const CORES_SUGERIDAS = [
    { nome: 'Tinta', cor: '#221A17' },
    { nome: 'Bordô', cor: '#6E1F24' },
    { nome: 'Dourado', cor: '#9A7428' },
    { nome: 'Branco', cor: '#FFFFFF' },
];

type Props = {
    valor: string;
    aoConfirmar: (cor: string) => void;
    desabilitado: boolean;
};

/**
 * O seletor de cor nativo dispara a cada movimento; só salva depois de uma pausa. Se o campo sair da
 * tela com uma cor ainda pendente, essa cor é salva na hora (uma única vez).
 */
export default function CampoDeCor({
    valor,
    aoConfirmar,
    desabilitado,
}: Props) {
    const [rascunho, setRascunho] = useState<string | null>(null);
    const temporizador = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendente = useRef<string | null>(null);
    const confirmarRef = useRef(aoConfirmar);
    const idDoRotulo = useId();

    useEffect(() => {
        confirmarRef.current = aoConfirmar;
    });

    useEffect(
        () => () => {
            if (temporizador.current) {
                clearTimeout(temporizador.current);
                temporizador.current = null;
            }

            const cor = pendente.current;
            pendente.current = null;

            if (cor !== null) {
                confirmarRef.current(cor);
            }
        },
        [],
    );

    const mudar = (cor: string) => {
        setRascunho(cor);
        pendente.current = cor;

        if (temporizador.current) {
            clearTimeout(temporizador.current);
        }

        temporizador.current = setTimeout(() => {
            temporizador.current = null;

            const confirmar = pendente.current;
            pendente.current = null;
            setRascunho(null);

            if (confirmar !== null) {
                confirmarRef.current(confirmar);
            }
        }, 400);
    };

    /** Atalho de cor: cancela um seletor livre pendente e salva na hora. */
    const escolherSugerida = (cor: string) => {
        if (temporizador.current) {
            clearTimeout(temporizador.current);
            temporizador.current = null;
        }

        pendente.current = null;
        setRascunho(null);

        if (cor.toUpperCase() !== valor.toUpperCase()) {
            aoConfirmar(cor);
        }
    };

    const atual = (rascunho ?? valor).toUpperCase();

    return (
        <div className="flex flex-col gap-1.5">
            <span id={idDoRotulo} className="text-xs text-muted-foreground">
                Cor
            </span>
            <div
                role="group"
                aria-labelledby={idDoRotulo}
                className="flex items-center gap-1"
            >
                {CORES_SUGERIDAS.map((sugestao) => (
                    <button
                        key={sugestao.cor}
                        type="button"
                        disabled={desabilitado}
                        aria-label={`Cor ${sugestao.nome} ${sugestao.cor}`}
                        aria-pressed={atual === sugestao.cor}
                        onClick={() => escolherSugerida(sugestao.cor)}
                        className="flex size-11 shrink-0 items-center justify-center rounded-md transition-colors hover:bg-card disabled:pointer-events-none disabled:opacity-50"
                    >
                        <span
                            aria-hidden="true"
                            className={cn(
                                'size-6 rounded-full border',
                                atual === sugestao.cor &&
                                    'ring-2 ring-primary ring-offset-2 ring-offset-card',
                            )}
                            style={{ background: sugestao.cor }}
                        />
                    </button>
                ))}

                <div className="relative ml-1 flex h-11 min-w-0 flex-1 items-center gap-2 rounded-md border bg-background px-2">
                    <span
                        aria-hidden="true"
                        className="size-5 shrink-0 rounded-full border"
                        style={{ background: rascunho ?? valor }}
                    />
                    <span className="font-mono text-xs">{atual}</span>
                    <input
                        type="color"
                        aria-label={`Escolher outra cor, atual ${atual}`}
                        value={rascunho ?? valor}
                        disabled={desabilitado}
                        onChange={(evento) => mudar(evento.target.value)}
                        className="absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
                    />
                </div>
            </div>
        </div>
    );
}
