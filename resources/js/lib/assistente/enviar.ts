import { cabecalhos, erroDaResposta } from '@/lib/http';
import { eventoDoAssistente } from './eventos';
import { LeitorDeSse } from './sse';
import type { EventoDoAssistente } from '@/types/assistente';

/** Envia a mensagem e chama `aoReceber` a cada evento do stream. Erros HTTP antes do stream (422, 429, 503) viram ErroHttp. */
export async function enviarMensagem(
    url: string,
    texto: string,
    aoReceber: (evento: EventoDoAssistente) => void,
    signal: AbortSignal,
): Promise<void> {
    const resposta = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            ...cabecalhos({ 'Content-Type': 'application/json' }),
            Accept: 'text/event-stream',
        },
        body: JSON.stringify({ texto }),
        signal,
    });

    if (!resposta.ok || resposta.body === null) {
        throw await erroDaResposta(resposta);
    }

    const leitor = new LeitorDeSse();
    const decodificador = new TextDecoder();
    const corpo = resposta.body.getReader();

    for (;;) {
        const { done, value } = await corpo.read();

        if (done) {
            break;
        }

        for (const bruto of leitor.ler(
            decodificador.decode(value, { stream: true }),
        )) {
            const evento = eventoDoAssistente(bruto);

            if (evento !== null) {
                aoReceber(evento);
            }
        }
    }
}

/** GET em JSON, com as mesmas regras de erro do `enviarJson`. */
export async function buscarJson<T>(
    url: string,
    signal?: AbortSignal,
): Promise<T> {
    const resposta = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: cabecalhos(),
        signal,
    });

    if (!resposta.ok) {
        throw await erroDaResposta(resposta);
    }

    return (await resposta.json()) as T;
}
