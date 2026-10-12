import { ArrowRight, Square } from 'lucide-react';
import { useEffect, useRef, type KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';

const LIMITE = 2000;
const CONTADOR_A_PARTIR_DE = 1800;
/** Altura máxima do campo: cerca de seis linhas, depois disso ele rola. */
const ALTURA_MAXIMA = 160;

type Props = {
    valor: string;
    aoMudar: (valor: string) => void;
    enviando: boolean;
    sugestoes: string[];
    aoEnviar: () => void;
    aoParar: () => void;
    aoSugerir: (texto: string) => void;
};

export function CampoDeMensagem({
    valor,
    aoMudar,
    enviando,
    sugestoes,
    aoEnviar,
    aoParar,
    aoSugerir,
}: Props) {
    const campoRef = useRef<HTMLTextAreaElement>(null);
    const podeEnviar = !enviando && valor.trim() !== '';

    // O campo cresce com o texto até ALTURA_MAXIMA e depois rola.
    useEffect(() => {
        const campo = campoRef.current;

        if (campo === null) {
            return;
        }

        campo.style.height = 'auto';
        campo.style.height = `${Math.min(campo.scrollHeight, ALTURA_MAXIMA)}px`;
    }, [valor]);

    function aoTeclar(evento: KeyboardEvent<HTMLTextAreaElement>) {
        // Enter envia; Shift+Enter quebra a linha. Durante a composição de acentos, Enter não envia.
        if (
            evento.key !== 'Enter' ||
            evento.shiftKey ||
            evento.nativeEvent.isComposing
        ) {
            return;
        }

        evento.preventDefault();

        if (podeEnviar) {
            aoEnviar();
        }
    }

    return (
        <form
            className="flex flex-col gap-2.5 border-t px-4 pt-3 pb-4"
            onSubmit={(evento) => {
                evento.preventDefault();

                if (podeEnviar) {
                    aoEnviar();
                }
            }}
        >
            <div className="flex flex-wrap gap-1.5">
                {sugestoes.map((sugestao) => (
                    <button
                        key={sugestao}
                        type="button"
                        disabled={enviando}
                        onClick={() => aoSugerir(sugestao)}
                        className="min-h-11 rounded-full border bg-muted/40 px-3.5 text-sm text-foreground hover:bg-muted disabled:opacity-60"
                    >
                        {sugestao}
                    </button>
                ))}
            </div>

            <label htmlFor="mensagem-assistente" className="sr-only">
                Mensagem para o assistente
            </label>
            <div className="flex items-end gap-2 rounded-2xl border bg-background py-1.5 pr-1.5 pl-3.5">
                <textarea
                    ref={campoRef}
                    id="mensagem-assistente"
                    rows={2}
                    maxLength={LIMITE}
                    value={valor}
                    disabled={enviando}
                    placeholder="Pergunte sobre a agenda ou os certificados…"
                    onChange={(evento) => aoMudar(evento.target.value)}
                    onKeyDown={aoTeclar}
                    className="max-h-40 min-h-0 flex-1 resize-none bg-transparent py-2 outline-none placeholder:text-muted-foreground disabled:opacity-60"
                />
                {enviando ? (
                    <Button
                        type="button"
                        variant="outline"
                        aria-label="Parar resposta"
                        onClick={aoParar}
                        className="size-11 shrink-0 rounded-xl"
                    >
                        <Square aria-hidden="true" className="fill-current" />
                    </Button>
                ) : (
                    <Button
                        type="submit"
                        aria-label="Enviar mensagem"
                        disabled={!podeEnviar}
                        className="size-11 shrink-0 rounded-xl"
                    >
                        <ArrowRight aria-hidden="true" />
                    </Button>
                )}
            </div>

            <p className="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                <span>
                    O assistente só grava algo depois que você confirmar.
                </span>
                {valor.length >= CONTADOR_A_PARTIR_DE && (
                    <span className="shrink-0 tabular-nums">
                        {valor.length}/{LIMITE}
                    </span>
                )}
            </p>
        </form>
    );
}
