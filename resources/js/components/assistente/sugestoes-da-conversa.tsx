import { Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import SugestoesController from '@/actions/App/Http/Controllers/Assistente/SugestoesController';
import { buscarJson } from '@/lib/assistente/enviar';

export type Sugestao = { texto: string; pergunta: string; destaque: boolean };

export type SugestoesDaSituacao = {
    situacao: 'primeiro' | 'pendencias' | 'tranquilo';
    saudacao: string;
    abertura: string;
    rotulo: string;
    sugestoes: Sugestao[];
};

/** Sem resposta do servidor (offline, erro): as sugestões de sempre, sem números. */
export const SUGESTOES_DE_RESERVA: SugestoesDaSituacao = {
    situacao: 'tranquilo',
    saudacao: 'Como posso ajudar?',
    abertura:
        'Pergunte sobre a agenda ou os certificados. Para cadastrar espaços, equipamentos ou grupos de uma vez, anexe ou arraste uma planilha.',
    rotulo: 'Para começar',
    sugestoes: [
        {
            texto: 'O que tem amanhã?',
            pergunta: 'O que tem na agenda amanhã?',
            destaque: false,
        },
        {
            texto: 'Solicitações pendentes',
            pergunta: 'Quais solicitações estão esperando resposta?',
            destaque: false,
        },
        {
            texto: 'Reservar um espaço',
            pergunta: 'Quero reservar um espaço.',
            destaque: false,
        },
    ],
};

type Props = {
    desabilitado: boolean;
    aoSugerir: (pergunta: string) => void;
};

/**
 * Abertura da conversa vazia: o assistente cumprimenta e oferece por onde começar, conforme a situação da agenda.
 * Só aparece antes da primeira mensagem; depois a conversa segue sem sugestões.
 */
export function SugestoesDaConversa({ desabilitado, aoSugerir }: Props) {
    const [dados, setDados] = useState<SugestoesDaSituacao | null>(null);

    useEffect(() => {
        const controle = new AbortController();

        buscarJson<SugestoesDaSituacao>(
            SugestoesController.url(),
            controle.signal,
        )
            .then(setDados)
            .catch(() => {
                if (!controle.signal.aborted) {
                    setDados(SUGESTOES_DE_RESERVA);
                }
            });

        return () => controle.abort();
    }, []);

    if (dados === null) {
        return null;
    }

    return (
        <div className="slide-in-from-bottom-1.5 mt-auto flex animate-in flex-col gap-3 duration-300 fade-in motion-reduce:animate-none">
            <span
                aria-hidden="true"
                className="flex size-8 items-center justify-center rounded-full bg-dourado-claro/35 text-dourado"
            >
                <Sparkles className="size-4" />
            </span>
            <p className="font-serif text-2xl leading-tight font-semibold">
                {dados.saudacao}
            </p>
            <p className="text-muted-foreground">{dados.abertura}</p>

            <div className="mt-1 flex flex-col gap-1.5">
                <span className="text-xs font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                    {dados.rotulo}
                </span>
                <ul aria-label="Sugestões" className="flex flex-wrap gap-1.5">
                    {dados.sugestoes.map((sugestao) => (
                        <li key={sugestao.texto}>
                            <button
                                type="button"
                                disabled={desabilitado}
                                onClick={() => aoSugerir(sugestao.pergunta)}
                                className="flex min-h-8 items-center gap-1.5 rounded-full border bg-card px-3 text-[13px] text-foreground transition-colors hover:border-dourado-claro hover:bg-dourado-claro/25 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-60"
                            >
                                {sugestao.destaque && (
                                    <span
                                        aria-hidden="true"
                                        className="size-[7px] rounded-full bg-dourado"
                                    />
                                )}
                                {sugestao.texto}
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
