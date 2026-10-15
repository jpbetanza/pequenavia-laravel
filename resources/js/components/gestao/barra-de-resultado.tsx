import { formatarNumero } from '@/lib/gestao/formatar';
import { cn } from '@/lib/utils';

export type Desfecho = 'resolvida' | 'parcial' | 'nao_resolvida' | 'aguardando';

export type Contagens = Record<Desfecho, number>;

const DESFECHOS: Desfecho[] = [
    'resolvida',
    'parcial',
    'nao_resolvida',
    'aguardando',
];

const ROTULOS: Record<Desfecho, string> = {
    resolvida: 'Resolvida',
    parcial: 'Parcial',
    nao_resolvida: 'Não resolvida',
    aguardando: 'Aguardando',
};

const CORES: Record<Desfecho, string> = {
    resolvida: 'bg-[#24513F]',
    parcial: 'bg-[#D9B97A]',
    nao_resolvida: 'bg-[#A33A2A]',
    aguardando: 'bg-[#CDBFAC]',
};

type Props = Contagens & {
    className?: string;
};

/**
 * Barra segmentada com a divisão de um caso de uso entre os desfechos, que não se sobrepõem (aguardando é a conversa
 * que parou esperando a secretaria). Os números vão para leitores de tela.
 */
export function BarraDeResultado({ className, ...contagens }: Props) {
    const total = DESFECHOS.reduce(
        (soma, desfecho) => soma + contagens[desfecho],
        0,
    );
    const descricao =
        total === 0
            ? 'Nenhuma conversa neste caso de uso.'
            : DESFECHOS.map(
                  (desfecho) =>
                      `${ROTULOS[desfecho]} ${formatarNumero(contagens[desfecho])}`,
              ).join(', ');

    return (
        <div
            className={cn(
                'relative flex h-3.5 overflow-hidden rounded-[4px] bg-secondary',
                className,
            )}
        >
            {DESFECHOS.filter((desfecho) => contagens[desfecho] > 0).map(
                (desfecho) => (
                    <span
                        key={desfecho}
                        aria-hidden="true"
                        className={cn('block h-full', CORES[desfecho])}
                        style={{
                            width: `${(contagens[desfecho] / total) * 100}%`,
                        }}
                    />
                ),
            )}
            <span className="sr-only">{descricao}</span>
        </div>
    );
}

/** Legenda das cores da barra, para o cabeçalho da matriz. */
export function LegendaDeResultado() {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            {DESFECHOS.map((desfecho) => (
                <li key={desfecho} className="flex items-center gap-1.5">
                    <span
                        aria-hidden="true"
                        className={cn(
                            'size-2.5 rounded-[3px]',
                            CORES[desfecho],
                        )}
                    />
                    {ROTULOS[desfecho]}
                </li>
            ))}
        </ul>
    );
}
