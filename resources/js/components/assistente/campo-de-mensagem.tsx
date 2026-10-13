import { ArrowRight, Paperclip, Square } from 'lucide-react';
import {
    useEffect,
    useRef,
    type ClipboardEvent,
    type KeyboardEvent,
} from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    type AnexoPreparado,
    DICA_DOS_ANEXOS,
    EXTENSOES_ACEITAS,
    MAX_ANEXOS,
} from '@/lib/assistente/anexos';
import { Anexo } from './anexo';

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
    /** Anexos prontos para ir com a próxima mensagem. */
    anexos: AnexoPreparado[];
    /** Arquivos sendo lidos (planilhas grandes levam um instante). */
    preparandoAnexos: boolean;
    aoEscolherArquivos: (arquivos: File[]) => void;
    aoRemoverAnexo: (id: string) => void;
};

export function CampoDeMensagem({
    valor,
    aoMudar,
    enviando,
    sugestoes,
    aoEnviar,
    aoParar,
    aoSugerir,
    anexos,
    preparandoAnexos,
    aoEscolherArquivos,
    aoRemoverAnexo,
}: Props) {
    const campoRef = useRef<HTMLTextAreaElement>(null);
    const seletorRef = useRef<HTMLInputElement>(null);
    const podeEnviar =
        !enviando &&
        !preparandoAnexos &&
        (valor.trim() !== '' || anexos.length > 0);
    const cheio = anexos.length >= MAX_ANEXOS;

    // O campo cresce com o texto até ALTURA_MAXIMA e depois rola.
    useEffect(() => {
        const campo = campoRef.current;

        if (campo === null) {
            return;
        }

        campo.style.height = 'auto';
        const altura = campo.scrollHeight;
        // Com o painel escondido (display: none) o campo mede 0; fixar 0px o deixaria com a altura
        // só do padding, cortando o placeholder. Nesse caso vale a altura natural (rows).
        campo.style.height =
            altura === 0 ? '' : `${Math.min(altura, ALTURA_MAXIMA)}px`;
    }, [valor]);

    // Colar um arquivo (ex.: um print) no campo anexa o arquivo em vez de colar o nome dele.
    function aoColar(evento: ClipboardEvent<HTMLTextAreaElement>) {
        const arquivos = Array.from(evento.clipboardData.files);

        if (arquivos.length === 0) {
            return;
        }

        evento.preventDefault();
        aoEscolherArquivos(arquivos);
    }

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
            <input
                ref={seletorRef}
                type="file"
                multiple
                accept={EXTENSOES_ACEITAS}
                className="hidden"
                tabIndex={-1}
                aria-hidden="true"
                onChange={(evento) => {
                    const arquivos = Array.from(evento.target.files ?? []);

                    // Limpa para que escolher o mesmo arquivo de novo dispare o change.
                    evento.target.value = '';

                    if (arquivos.length > 0) {
                        aoEscolherArquivos(arquivos);
                    }
                }}
            />
            <div className="flex flex-col rounded-2xl border bg-background focus-within:ring-2 focus-within:ring-ring/40">
                {(anexos.length > 0 || preparandoAnexos) && (
                    <ul
                        aria-label="Arquivos anexados"
                        className="flex flex-col gap-1.5 px-1.5 pt-1.5"
                    >
                        {anexos.map((anexo) => (
                            <li key={anexo.id} className="flex min-w-0">
                                <Anexo
                                    anexo={anexo}
                                    aoRemover={() => aoRemoverAnexo(anexo.id)}
                                />
                            </li>
                        ))}
                        {preparandoAnexos && (
                            <li className="flex items-center gap-2 px-2 py-1.5 text-sm text-muted-foreground">
                                <Spinner />
                                Lendo o arquivo…
                            </li>
                        )}
                    </ul>
                )}
                <div className="flex items-end gap-1 py-1.5 pr-1.5 pl-1.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Anexar arquivo"
                        title={
                            cheio
                                ? `Até ${MAX_ANEXOS} arquivos por mensagem`
                                : DICA_DOS_ANEXOS
                        }
                        disabled={cheio}
                        onClick={() => seletorRef.current?.click()}
                        className="size-11 shrink-0 rounded-xl text-muted-foreground"
                    >
                        <Paperclip aria-hidden="true" />
                    </Button>
                    <textarea
                        ref={campoRef}
                        id="mensagem-assistente"
                        rows={2}
                        maxLength={LIMITE}
                        value={valor}
                        disabled={enviando}
                        placeholder="Pergunte ou anexe um arquivo…"
                        onChange={(evento) => aoMudar(evento.target.value)}
                        onKeyDown={aoTeclar}
                        onPaste={aoColar}
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
                            <Square
                                aria-hidden="true"
                                className="fill-current"
                            />
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
