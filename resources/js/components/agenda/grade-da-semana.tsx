import { Repeat } from 'lucide-react';
import { corDoEspaco } from '@/lib/agenda/cores';
import { diaDaSemana, horaDe } from '@/lib/agenda/datas';
import {
    ALTURA_DA_GRADE,
    colunasLadoALado,
    HORA_FINAL,
    HORA_INICIAL,
    horaDoClique,
    PIXELS_POR_HORA,
    posicaoDoAgora,
    posicaoDoBloco,
} from '@/lib/agenda/grade';
import { cn } from '@/lib/utils';
import type {
    EspacoDoCalendario,
    ReservaDoCalendario,
} from '@/types/agendamentos';

type Props = {
    /** Datas exibidas, "YYYY-MM-DD". Sete na semana; uma na visão dia. */
    dias: string[];
    /** Dia de hoje, "YYYY-MM-DD", para destacar a coluna e desenhar a linha "agora". */
    hoje: string;
    /** "agora" ISO no fuso da agenda. */
    agora: string;
    reservas: ReservaDoCalendario[];
    espacos: EspacoDoCalendario[];
    /** Clique num espaço vazio. A hora já vem arredondada para 30 minutos. */
    onNovaReserva: (data: string, hora?: string, espacoId?: string) => void;
    onEditarReserva: (reserva: ReservaDoCalendario) => void;
};

const SIGLAS_DOS_DIAS = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB'];

const HORAS = Array.from(
    { length: HORA_FINAL - HORA_INICIAL },
    (_, indice) => HORA_INICIAL + indice,
);

const LINHAS_DA_GRADE = `repeating-linear-gradient(to bottom, var(--border) 0, var(--border) 1px, transparent 1px, transparent ${PIXELS_POR_HORA}px)`;

export default function GradeDaSemana({
    dias,
    hoje,
    agora,
    reservas,
    espacos,
    onNovaReserva,
    onEditarReserva,
}: Props) {
    const espacoPorId = new Map(espacos.map((espaco) => [espaco.id, espaco]));
    const posicaoAgora = dias.includes(hoje)
        ? posicaoDoAgora(horaDe(agora))
        : null;

    return (
        <div className="overflow-x-auto">
            <div
                className="flex flex-col"
                style={{ minWidth: dias.length > 1 ? 720 : 360 }}
            >
                <div
                    className="grid border-b"
                    style={{
                        gridTemplateColumns: `52px repeat(${dias.length}, minmax(0, 1fr))`,
                    }}
                >
                    <span aria-hidden="true" />
                    {dias.map((dia) => {
                        const ehHoje = dia === hoje;

                        return (
                            <div
                                key={dia}
                                className={cn(
                                    'flex flex-col items-center py-2.5 leading-tight',
                                    ehHoje && 'text-primary',
                                )}
                            >
                                <span
                                    className={cn(
                                        'text-xs text-muted-foreground',
                                        ehHoje && 'font-semibold text-primary',
                                    )}
                                >
                                    {SIGLAS_DOS_DIAS[diaDaSemana(dia)]}
                                </span>
                                <span
                                    className={cn(
                                        'text-xl font-semibold',
                                        ehHoje && 'font-bold',
                                    )}
                                >
                                    {Number(dia.slice(8))}
                                </span>
                            </div>
                        );
                    })}
                </div>

                <div
                    className="grid"
                    style={{
                        gridTemplateColumns: `52px repeat(${dias.length}, minmax(0, 1fr))`,
                        height: ALTURA_DA_GRADE,
                    }}
                >
                    <div className="relative text-[11px] text-muted-foreground">
                        {HORAS.map((hora) => (
                            <span
                                key={hora}
                                className="absolute right-2 leading-none"
                                style={{
                                    top:
                                        (hora - HORA_INICIAL) *
                                            PIXELS_POR_HORA +
                                        2,
                                }}
                            >
                                {String(hora).padStart(2, '0')}:00
                            </span>
                        ))}
                    </div>

                    {dias.map((dia) => (
                        <ColunaDoDia
                            key={dia}
                            dia={dia}
                            ehHoje={dia === hoje}
                            reservas={reservas.filter(
                                (reserva) => reserva.data === dia,
                            )}
                            espacoPorId={espacoPorId}
                            posicaoAgora={dia === hoje ? posicaoAgora : null}
                            onNovaReserva={onNovaReserva}
                            onEditarReserva={onEditarReserva}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}

type ColunaProps = {
    dia: string;
    ehHoje: boolean;
    reservas: ReservaDoCalendario[];
    espacoPorId: Map<string, EspacoDoCalendario>;
    posicaoAgora: number | null;
    onNovaReserva: Props['onNovaReserva'];
    onEditarReserva: Props['onEditarReserva'];
};

function ColunaDoDia({
    dia,
    ehHoje,
    reservas,
    espacoPorId,
    posicaoAgora,
    onNovaReserva,
    onEditarReserva,
}: ColunaProps) {
    const posicoes = colunasLadoALado(reservas);

    return (
        <div
            className={cn(
                'relative cursor-pointer border-l',
                ehHoje && 'bg-primary/5',
            )}
            style={{ backgroundImage: LINHAS_DA_GRADE }}
            onClick={(evento) => {
                const topo = evento.currentTarget.getBoundingClientRect().top;

                onNovaReserva(dia, horaDoClique(evento.clientY - topo));
            }}
        >
            {reservas.map((reserva) => {
                const { topo, altura } = posicaoDoBloco(
                    reserva.inicio,
                    reserva.fim,
                );
                const posicao = posicoes.get(reserva.id);

                if (altura === 0 || posicao === undefined) {
                    return null;
                }

                return (
                    <BlocoDaReserva
                        key={reserva.id}
                        reserva={reserva}
                        espaco={espacoPorId.get(reserva.espaco_id) ?? null}
                        topo={topo}
                        altura={altura}
                        coluna={posicao.coluna}
                        totalColunas={posicao.totalColunas}
                        onEditar={onEditarReserva}
                    />
                );
            })}

            {posicaoAgora !== null && (
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute right-0 left-0 z-10"
                    style={{ top: posicaoAgora }}
                >
                    <span className="absolute -top-1 -left-1.5 size-2.5 rounded-full bg-destructive" />
                    <span className="block h-0.5 bg-destructive" />
                </div>
            )}
        </div>
    );
}

type BlocoProps = {
    reserva: ReservaDoCalendario;
    espaco: EspacoDoCalendario | null;
    topo: number;
    altura: number;
    coluna: number;
    totalColunas: number;
    onEditar: (reserva: ReservaDoCalendario) => void;
};

function BlocoDaReserva({
    reserva,
    espaco,
    topo,
    altura,
    coluna,
    totalColunas,
    onEditar,
}: BlocoProps) {
    const paleta = corDoEspaco(espaco?.cor ?? 'azul');
    const nomeDoEspaco = espaco?.nome ?? 'Sem espaço';

    return (
        <button
            type="button"
            aria-label={`${reserva.titulo}, ${nomeDoEspaco}, ${reserva.inicio}–${reserva.fim}`}
            onClick={(evento) => {
                // O clique no bloco não deve contar como clique no espaço vazio.
                evento.stopPropagation();
                onEditar(reserva);
            }}
            className="absolute flex cursor-pointer flex-col gap-0.5 overflow-hidden rounded-lg border-l-[3px] px-2 py-1 text-left text-xs transition-[filter] outline-none hover:brightness-95 focus-visible:ring-[3px] focus-visible:ring-ring/50"
            style={{
                top: topo,
                height: altura,
                left: `calc(${(coluna * 100) / totalColunas}% + 4px)`,
                width: `calc(${100 / totalColunas}% - 8px)`,
                backgroundColor: paleta.fundo,
                borderLeftColor: paleta.forte,
                color: paleta.texto,
            }}
        >
            <span className="flex min-w-0 items-center gap-1 leading-tight font-semibold">
                <span className="truncate">{reserva.titulo}</span>
                {reserva.serie_id !== null && (
                    <Repeat className="size-3 shrink-0" aria-hidden="true" />
                )}
            </span>
            <span className="truncate leading-tight">
                {reserva.inicio}–{reserva.fim} · {nomeDoEspaco}
            </span>
        </button>
    );
}
