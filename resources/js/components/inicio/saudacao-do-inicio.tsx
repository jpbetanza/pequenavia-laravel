import { usePage } from '@inertiajs/react';
import { ArrowUp, Sparkles } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { useAssistente } from '@/hooks/use-assistente';
import { saudacao } from '@/lib/inicio/resumo';
import type { DiaDoInicio } from '@/types/inicio';

const SUGESTOES = [
    'O que tem amanhã?',
    'Solicitações pendentes',
    'Certificados emitidos este mês',
];

type Props = {
    hoje: DiaDoInicio;
    nome: string;
    resumo: string;
};

/** Primeira letra maiúscula, para o rótulo da data. */
function comInicialMaiuscula(texto: string): string {
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function PerguntaAoAssistente() {
    const { perguntar } = useAssistente();
    const [texto, setTexto] = useState('');
    const vazia = texto.trim() === '';

    function enviar(pergunta: string) {
        perguntar(pergunta);
        setTexto('');
    }

    function enviarDoFormulario(evento: FormEvent<HTMLFormElement>) {
        evento.preventDefault();

        if (!vazia) {
            enviar(texto);
        }
    }

    return (
        <form onSubmit={enviarDoFormulario} className="flex flex-col gap-3">
            <label
                htmlFor="pergunta-do-inicio"
                className="flex items-center gap-2 text-sm font-semibold"
            >
                <Sparkles aria-hidden="true" className="size-4" />
                Pergunte ao assistente
            </label>
            <div className="flex items-center gap-2 rounded-2xl bg-background py-1.5 pr-1.5 pl-4 text-foreground">
                <input
                    id="pergunta-do-inicio"
                    type="text"
                    value={texto}
                    onChange={(evento) => setTexto(evento.target.value)}
                    placeholder="Ex.: tem sala livre quinta à noite para 20 pessoas?"
                    className="min-h-11 min-w-0 flex-1 bg-transparent text-[15px] outline-none placeholder:text-muted-foreground"
                />
                <Button
                    type="submit"
                    size="icon"
                    disabled={vazia}
                    aria-label="Enviar pergunta"
                    className="size-11 shrink-0 rounded-xl"
                >
                    <ArrowUp aria-hidden="true" />
                </Button>
            </div>
            <div className="flex flex-wrap gap-2">
                {SUGESTOES.map((sugestao) => (
                    <button
                        key={sugestao}
                        type="button"
                        onClick={() => enviar(sugestao)}
                        className="min-h-11 rounded-full border border-background/30 px-3.5 text-sm hover:bg-background/10"
                    >
                        {sugestao}
                    </button>
                ))}
            </div>
        </form>
    );
}

/** Cartão escuro do topo: a data, a saudação com o resumo do dia e, se o assistente estiver ligado, a pergunta. */
export function SaudacaoDoInicio({ hoje, nome, resumo }: Props) {
    const { assistente } = usePage().props;

    return (
        <section
            aria-labelledby="saudacao-do-inicio"
            className="grid min-w-0 grid-cols-1 items-center gap-7 rounded-[22px] bg-foreground p-6 text-background sm:p-7 @4xl:grid-cols-2"
        >
            <div className="flex flex-col gap-2">
                <p className="text-[13px] font-semibold tracking-[0.14em] text-background/70 uppercase">
                    {comInicialMaiuscula(hoje.rotulo)}
                </p>
                <h1
                    id="saudacao-do-inicio"
                    className="font-serif text-4xl leading-none font-semibold sm:text-5xl"
                >
                    {saudacao(hoje.periodo, nome)}
                </h1>
                <p className="text-base text-background/80">{resumo}</p>
            </div>

            {assistente?.disponivel && <PerguntaAoAssistente />}
        </section>
    );
}
