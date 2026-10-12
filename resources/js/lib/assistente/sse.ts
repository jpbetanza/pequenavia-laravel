export type EventoSse = { evento: string; dados: string };

/** Lê um stream `text/event-stream` em pedaços arbitrários e devolve só os eventos completos. */
export class LeitorDeSse {
    /** Sobra do último pedaço: texto sem terminador de evento, normalizado, mais um \r final retido. */
    private resto = '';

    ler(pedaco: string): EventoSse[] {
        // Um \r no fim pode ser o início de um \r\n partido entre pedaços: guarda para o próximo.
        const bruto = this.resto + pedaco;
        const retido = bruto.endsWith('\r') ? '\r' : '';
        let buffer = (retido ? bruto.slice(0, -1) : bruto).replace(
            /\r\n/g,
            '\n',
        );
        const eventos: EventoSse[] = [];
        let fim = buffer.indexOf('\n\n');

        while (fim !== -1) {
            const bloco = buffer.slice(0, fim);
            buffer = buffer.slice(fim + 2);
            let evento = 'message';
            const dados: string[] = [];

            for (const linha of bloco.split('\n')) {
                if (linha.startsWith(':')) {
                    continue;
                }

                if (linha.startsWith('event:')) {
                    evento = linha.slice(6).trim();
                } else if (linha.startsWith('data:')) {
                    dados.push(linha.slice(5).replace(/^ /, ''));
                }
            }

            if (dados.length > 0) {
                eventos.push({ evento, dados: dados.join('\n') });
            }

            fim = buffer.indexOf('\n\n');
        }

        this.resto = buffer + retido;
        return eventos;
    }
}
