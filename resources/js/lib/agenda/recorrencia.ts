import {
    deslocarMes,
    diaDaSemana,
    diferencaEmDias,
    somarDias,
} from '@/lib/agenda/datas';

/** Mesmas opções do servidor (`App\Services\Agenda\Recorrencia`). */
export type Repeticao = 'nao' | 'semanal' | 'mensal';

/** Máximo de ocorrências de uma série. Espelha `Recorrencia::LIMITE`. */
export const LIMITE_DE_OCORRENCIAS = 52;

const NOMES_DOS_DIAS = [
    'domingo',
    'segunda-feira',
    'terça-feira',
    'quarta-feira',
    'quinta-feira',
    'sexta-feira',
    'sábado',
];

const ORDINAIS = ['1º', '2º', '3º', '4º', '5º'];

/**
 * Datas de uma série, na mesma regra do servidor: a data inicial é sempre a primeira;
 * a semanal repete de 7 em 7 dias; a mensal repete o n-ésimo dia da semana do mês
 * (o 5º sábado, por exemplo, não existe em todo mês e esse mês é pulado).
 * Sem repetição, ou sem data final, devolve só a data inicial.
 */
export function datasDaRecorrencia(
    inicio: string,
    tipo: Repeticao,
    ate: string | null,
): string[] {
    if (tipo === 'nao' || ate === null) {
        return [inicio];
    }

    return tipo === 'semanal'
        ? datasSemanais(inicio, ate)
        : datasMensais(inicio, ate);
}

function datasSemanais(inicio: string, ate: string): string[] {
    const datas = [inicio];

    for (
        let data = somarDias(inicio, 7);
        datas.length < LIMITE_DE_OCORRENCIAS && diferencaEmDias(ate, data) <= 0;
        data = somarDias(data, 7)
    ) {
        datas.push(data);
    }

    return datas;
}

function datasMensais(inicio: string, ate: string): string[] {
    const datas = [inicio];
    const posicaoNoMes = Math.floor((Number(inicio.slice(8)) - 1) / 7) + 1;

    for (
        let mes = deslocarMes(inicio, 1);
        datas.length < LIMITE_DE_OCORRENCIAS && diferencaEmDias(ate, mes) <= 0;
        mes = deslocarMes(mes, 1)
    ) {
        const ocorrencia = nEsimoDiaDaSemanaDoMes(inicio, mes, posicaoNoMes);

        if (ocorrencia !== null && diferencaEmDias(ate, ocorrencia) <= 0) {
            datas.push(ocorrencia);
        }
    }

    return datas;
}

/** Dia `posicao` do mês de `primeiroDoMes` com o mesmo dia da semana de `inicio`, ou null se o mês não chega lá. */
function nEsimoDiaDaSemanaDoMes(
    inicio: string,
    primeiroDoMes: string,
    posicao: number,
): string | null {
    const deslocamento =
        ((diaDaSemana(inicio) - diaDaSemana(primeiroDoMes) + 7) % 7) +
        (posicao - 1) * 7;
    const ultimoDoMes = somarDias(deslocarMes(primeiroDoMes, 1), -1);

    if (deslocamento > Number(ultimoDoMes.slice(8)) - 1) {
        return null;
    }

    return somarDias(primeiroDoMes, deslocamento);
}

function dataPtBr(iso: string): string {
    return `${iso.slice(8, 10)}/${iso.slice(5, 7)}/${iso.slice(0, 4)}`;
}

/**
 * Frase do resumo da série, com o número de datas que a regra gera.
 * Ex.: "No 2º sábado de cada mês, até 12/12/2026 · 3 datas".
 */
export function resumoDaRecorrencia(
    inicio: string,
    tipo: Repeticao,
    ate: string | null,
): string {
    if (tipo === 'nao') {
        return 'Não repete';
    }

    if (ate === null) {
        return 'Escolha até quando repete';
    }

    const quantidade = datasDaRecorrencia(inicio, tipo, ate).length;
    const plural = quantidade === 1 ? 'data' : 'datas';
    const trecho =
        tipo === 'semanal'
            ? 'Toda semana'
            : `No ${ORDINAIS[Math.floor((Number(inicio.slice(8)) - 1) / 7)]} ${NOMES_DOS_DIAS[diaDaSemana(inicio)]} de cada mês`;

    return `${trecho}, até ${dataPtBr(ate)} · ${quantidade} ${plural}`;
}

/** Data final sugerida quando a pessoa escolhe repetir: três meses depois, em dias corridos. */
export function ateSugerido(inicio: string): string {
    return somarDias(inicio, 90);
}
