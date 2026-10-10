/** Sentinela do seletor de espaço para "Qualquer espaço disponível". Vira null no envio. */
export const QUALQUER_ESPACO = 'qualquer';

const MAXIMO_DE_DIGITOS = 11;

/**
 * Máscara simples de telefone brasileiro: "(11) 90000-0000" ou "(11) 3000-0000".
 * Guarda no máximo 11 dígitos; o servidor aceita de 10 a 13 dígitos e tira o que não for número.
 */
export function mascararTelefone(valor: string): string {
    const digitos = valor.replace(/\D/g, '').slice(0, MAXIMO_DE_DIGITOS);

    if (digitos.length === 0) {
        return '';
    }

    const ddd = digitos.slice(0, 2);
    const numero = digitos.slice(2);

    if (digitos.length <= 2) {
        return `(${ddd}`;
    }

    if (numero.length <= 4) {
        return `(${ddd}) ${numero}`;
    }

    return `(${ddd}) ${numero.slice(0, -4)}-${numero.slice(-4)}`;
}

/**
 * Dicas do espaço escolhido: antecedência mínima e duração máxima, que só valem para o pedido público.
 * Lista vazia quando o espaço não tem regra nenhuma.
 */
export function dicasDoEspaco(espaco: {
    antecedencia_min_horas: number;
    duracao_max_min: number | null;
}): string[] {
    const dicas: string[] = [];

    if (espaco.antecedencia_min_horas > 0) {
        const horas = espaco.antecedencia_min_horas;

        dicas.push(
            `Pedidos para este espaço precisam de pelo menos ${horas} ${horas === 1 ? 'hora' : 'horas'} de antecedência.`,
        );
    }

    if (espaco.duracao_max_min !== null) {
        dicas.push(
            `Este espaço aceita no máximo ${espaco.duracao_max_min} minutos por pedido.`,
        );
    }

    return dicas;
}
