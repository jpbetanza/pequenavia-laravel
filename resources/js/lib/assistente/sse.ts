export type EventoSse = { evento: string; dados: string };

/** Lê um stream `text/event-stream` em pedaços arbitrários e devolve só os eventos completos. */
export class LeitorDeSse {
    private resto = '';

    ler(pedaco: string): EventoSse[] {
        this.resto += pedaco.replace(/\r\n/g, '\n');
        const eventos: EventoSse[] = [];
        let fim = this.resto.indexOf('\n\n');

        while (fim !== -1) {
            const bloco = this.resto.slice(0, fim);
            this.resto = this.resto.slice(fim + 2);
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

            fim = this.resto.indexOf('\n\n');
        }

        return eventos;
    }
}
