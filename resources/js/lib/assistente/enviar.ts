import { cabecalhos, erroDaResposta } from '@/lib/http';
import type { AnexoPreparado } from './anexos';
import { eventoDoAssistente } from './eventos';
import { LeitorDeSse } from './sse';
import type { EventoDoAssistente } from '@/types/assistente';

/**
 * Corpo multipart da mensagem: o texto e, para cada anexo, nome, tipo e o conteúdo já convertido (planilha, texto)
 * ou o arquivo (imagem, PDF).
 */
export function corpoDaMensagem(
    texto: string,
    anexos: AnexoPreparado[],
): FormData {
    const corpo = new FormData();

    if (texto !== '') {
        corpo.append('texto', texto);
    }

    anexos.forEach((anexo, indice) => {
        const campo = (nome: string) => `anexos[${indice}][${nome}]`;

        corpo.append(campo('nome'), anexo.nome);
        corpo.append(campo('tipo'), anexo.tipo);

        if (anexo.arquivo !== undefined) {
            corpo.append(campo('arquivo'), anexo.arquivo, anexo.nome);
        } else {
            corpo.append(campo('conteudo'), anexo.conteudo ?? '');
            corpo.append(campo('truncado'), anexo.truncado ? '1' : '0');
        }
    });

    return corpo;
}

/** Envia a mensagem e chama `aoReceber` a cada evento do stream. Erros HTTP antes do stream (422, 429, 503) viram ErroHttp. */
export async function enviarMensagem(
    url: string,
    texto: string,
    anexos: AnexoPreparado[],
    aoReceber: (evento: EventoDoAssistente) => void,
    signal: AbortSignal,
): Promise<void> {
    // Sem Content-Type: o navegador põe o multipart com a fronteira certa.
    const resposta = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { ...cabecalhos(), Accept: 'text/event-stream' },
        body: corpoDaMensagem(texto, anexos),
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
