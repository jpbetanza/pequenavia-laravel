import { formatarData, formatarNumero } from '@/lib/gestao/formatar';
import type { DiaDoPainel } from '@/types/gestao';

type Props = {
    dias: DiaDoPainel[];
};

const LARGURA = 600;
const ALTURA = 200;
const ALTURA_DAS_BARRAS = 180;
const INTERVALO_DOS_ROTULOS = 7;
const COR_RESOLVIDAS = '#6E1F24';
const COR_NAO_RESOLVIDAS = '#D9B97A';

/**
 * Conversas por dia, em barras empilhadas (resolvidas embaixo, não resolvidas em cima). Sem biblioteca:
 * é um SVG com a escala calculada aqui. O rótulo do eixo x aparece a cada 7 dias, contando do último.
 */
export function GraficoPorDia({ dias }: Props) {
    const total = dias.reduce(
        (soma, dia) => soma + dia.resolvidas + dia.nao_resolvidas,
        0,
    );
    const resolvidas = dias.reduce((soma, dia) => soma + dia.resolvidas, 0);
    const maior = Math.max(
        1,
        ...dias.map((dia) => dia.resolvidas + dia.nao_resolvidas),
    );
    const passo = dias.length > 0 ? LARGURA / dias.length : LARGURA;
    const larguraDaBarra = Math.max(2, passo * 0.7);

    const rotulos: number[] = [];

    for (let i = dias.length - 1; i >= 0; i -= INTERVALO_DOS_ROTULOS) {
        rotulos.unshift(i);
    }

    const pico = dias.reduce<DiaDoPainel | null>(
        (atual, dia) =>
            atual === null ||
            dia.resolvidas + dia.nao_resolvidas >
                atual.resolvidas + atual.nao_resolvidas
                ? dia
                : atual,
        null,
    );

    const resumo =
        total === 0 || pico === null
            ? 'Nenhuma conversa classificada no período.'
            : `Conversas por dia no período: ${formatarNumero(total)} no total, ${formatarNumero(resolvidas)} resolvidas e ${formatarNumero(total - resolvidas)} não resolvidas. O pico foi de ${formatarNumero(pico.resolvidas + pico.nao_resolvidas)} conversas em ${formatarData(pico.data)}.`;

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <span className="flex items-center gap-1.5">
                    <span
                        aria-hidden="true"
                        className="size-2.5 rounded-[3px] bg-primary"
                    />
                    Resolvidas
                </span>
                <span className="flex items-center gap-1.5">
                    <span
                        aria-hidden="true"
                        className="size-2.5 rounded-[3px] bg-dourado-claro"
                    />
                    Não resolvidas
                </span>
            </div>

            <svg
                viewBox={`0 0 ${LARGURA} ${ALTURA}`}
                role="img"
                aria-label={resumo}
                className="w-full"
            >
                <g stroke="#EDE5D8" strokeWidth="1">
                    {[0.25, 0.5, 0.75, 1].map((fracao) => {
                        const y = ALTURA_DAS_BARRAS * (1 - fracao);

                        return (
                            <line
                                key={fracao}
                                x1="0"
                                x2={LARGURA}
                                y1={y}
                                y2={y}
                            />
                        );
                    })}
                </g>

                {dias.map((dia, i) => {
                    const x = i * passo + (passo - larguraDaBarra) / 2;
                    const alturaResolvidas =
                        (dia.resolvidas / maior) * ALTURA_DAS_BARRAS;
                    const alturaNaoResolvidas =
                        (dia.nao_resolvidas / maior) * ALTURA_DAS_BARRAS;
                    const totalDoDia = dia.resolvidas + dia.nao_resolvidas;

                    if (totalDoDia === 0) {
                        return null;
                    }

                    return (
                        <g key={dia.data}>
                            <title>
                                {`${formatarData(dia.data)}: ${formatarNumero(totalDoDia)} conversas, ${formatarNumero(dia.resolvidas)} resolvidas`}
                            </title>
                            <rect
                                x={x}
                                y={ALTURA_DAS_BARRAS - alturaResolvidas}
                                width={larguraDaBarra}
                                height={alturaResolvidas}
                                rx="2"
                                fill={COR_RESOLVIDAS}
                            />
                            <rect
                                x={x}
                                y={
                                    ALTURA_DAS_BARRAS -
                                    alturaResolvidas -
                                    alturaNaoResolvidas
                                }
                                width={larguraDaBarra}
                                height={alturaNaoResolvidas}
                                rx="2"
                                fill={COR_NAO_RESOLVIDAS}
                            />
                        </g>
                    );
                })}

                {rotulos.map((i) => (
                    <text
                        key={dias[i].data}
                        x={
                            i === dias.length - 1
                                ? LARGURA
                                : i === 0
                                  ? 0
                                  : i * passo + passo / 2
                        }
                        y={ALTURA - 4}
                        textAnchor={
                            i === dias.length - 1
                                ? 'end'
                                : i === 0
                                  ? 'start'
                                  : 'middle'
                        }
                        className="fill-muted-foreground text-[12px]"
                    >
                        {formatarData(dias[i].data)}
                    </text>
                ))}
            </svg>
        </div>
    );
}
