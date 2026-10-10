import { Link } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { index as rotaDeSolicitacoes } from '@/routes/agendamentos/solicitacoes';
import { corDoEspaco } from '@/lib/agenda/cores';
import { formatarDiaCurto, horaDe } from '@/lib/agenda/datas';
import { HORA_FINAL, HORA_INICIAL, minutosDoHorario } from '@/lib/agenda/grade';
import { linkDoWhatsApp } from '@/lib/agenda/whatsapp';
import {
    estadoDaSolicitacao,
    type EstadoDaSolicitacao,
} from '@/lib/agenda/solicitacoes';
import {
    horarioDoLivre,
    intervaloDeMinutos,
    MINUTO_DA_VISAO_FINAL,
    MINUTO_DA_VISAO_INICIAL,
    ocupacaoDoEspaco,
} from '@/lib/agenda/ocupacao';
import { cn } from '@/lib/utils';
import { buttonVariants } from '@/components/ui/button';
import type {
    EspacoDoCalendario,
    ReservaDoCalendario,
    SolicitacaoDoCalendario,
} from '@/types/agendamentos';

type Props = {
    /** Dia exibido, "YYYY-MM-DD". */
    data: string;
    hoje: string;
    /** "agora" ISO no fuso da agenda. */
    agora: string;
    espacos: EspacoDoCalendario[];
    reservas: ReservaDoCalendario[];
    solicitacoes: SolicitacaoDoCalendario[];
    /** Abre o painel com espaço, dia e hora já escolhidos. */
    onReservar: (data: string, hora: string, espacoId: string) => void;
    onEditarReserva: (reserva: ReservaDoCalendario) => void;
};

const TOTAL_DE_MINUTOS = MINUTO_DA_VISAO_FINAL - MINUTO_DA_VISAO_INICIAL;
const ROTULOS_DAS_HORAS = Array.from(
    { length: HORA_FINAL - HORA_INICIAL },
    (_, indice) => HORA_INICIAL + indice,
);
const LINHAS_DAS_HORAS = `repeating-linear-gradient(to right, var(--border) 0, var(--border) 1px, transparent 1px, transparent ${100 / (HORA_FINAL - HORA_INICIAL)}%)`;
const HACHURA =
    'repeating-linear-gradient(135deg, var(--muted) 0, var(--muted) 6px, var(--background) 6px, var(--background) 12px)';

/** Posição de um horário na faixa do dia, em porcentagem. */
function percentual(minuto: number): number {
    return ((minuto - MINUTO_DA_VISAO_INICIAL) / TOTAL_DE_MINUTOS) * 100;
}

/** Visão do dia no computador: uma linha por espaço, com ocupação, bloqueios e solicitações pendentes. */
export default function OcupacaoDoDia({
    data,
    hoje,
    agora,
    espacos,
    reservas,
    solicitacoes,
    onReservar,
    onEditarReserva,
}: Props) {
    const reservasDoDia = reservas.filter((reserva) => reserva.data === data);
    const pendentes = solicitacoes.filter(
        (solicitacao) => solicitacao.data === data,
    );
    const minutoAgora = minutosDoHorario(horaDe(agora));
    const agoraNaFaixa =
        data === hoje &&
        minutoAgora >= MINUTO_DA_VISAO_INICIAL &&
        minutoAgora < MINUTO_DA_VISAO_FINAL
            ? percentual(minutoAgora)
            : null;

    return (
        <div className="flex flex-col gap-6 p-4">
            <section
                aria-label="Ocupação por espaço"
                className="overflow-hidden rounded-xl border bg-card"
            >
                <div className="overflow-x-auto">
                    <div className="min-w-[860px]">
                        <div className="grid grid-cols-[200px_1fr] border-b bg-muted/40">
                            <span className="px-4 py-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Espaço
                            </span>
                            <div className="relative h-9 text-[11px] text-muted-foreground">
                                {ROTULOS_DAS_HORAS.map((hora) => (
                                    <span
                                        key={hora}
                                        className="absolute top-2.5 pl-1"
                                        style={{
                                            left: `${percentual(hora * 60)}%`,
                                        }}
                                    >
                                        {String(hora).padStart(2, '0')}
                                    </span>
                                ))}
                            </div>
                        </div>

                        {espacos.length === 0 && (
                            <p className="p-4 text-sm text-muted-foreground">
                                Nenhum espaço selecionado no filtro.
                            </p>
                        )}

                        {espacos.map((espaco) => (
                            <LinhaDoEspaco
                                key={espaco.id}
                                espaco={espaco}
                                data={data}
                                reservas={reservasDoDia.filter(
                                    (reserva) =>
                                        reserva.espaco_id === espaco.id,
                                )}
                                agoraNaFaixa={agoraNaFaixa}
                                onReservar={onReservar}
                                onEditarReserva={onEditarReserva}
                            />
                        ))}
                    </div>
                </div>
            </section>

            <section
                aria-labelledby="solicitacoes-do-dia"
                className="flex flex-col gap-3"
            >
                <h2
                    id="solicitacoes-do-dia"
                    className="flex flex-wrap items-baseline gap-x-3 font-serif text-2xl font-semibold"
                >
                    Solicitações para estes dias
                    <span className="font-sans text-sm font-normal text-muted-foreground">
                        feitas pelo formulário ·{' '}
                        <Link
                            href={rotaDeSolicitacoes()}
                            className="text-primary underline-offset-4 hover:underline"
                        >
                            ver todas
                        </Link>
                    </span>
                </h2>

                {pendentes.length === 0 ? (
                    <p className="rounded-xl border bg-card p-4 text-sm text-muted-foreground">
                        Nenhuma solicitação pendente para este dia.
                    </p>
                ) : (
                    <ul className="divide-y rounded-xl border bg-card">
                        {pendentes.map((solicitacao) => (
                            <LinhaDeSolicitacao
                                key={solicitacao.id}
                                solicitacao={solicitacao}
                                espacos={espacos}
                                reservas={reservasDoDia}
                            />
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}

type LinhaProps = {
    espaco: EspacoDoCalendario;
    data: string;
    reservas: ReservaDoCalendario[];
    agoraNaFaixa: number | null;
    onReservar: Props['onReservar'];
    onEditarReserva: Props['onEditarReserva'];
};

function LinhaDoEspaco({
    espaco,
    data,
    reservas,
    agoraNaFaixa,
    onReservar,
    onEditarReserva,
}: LinhaProps) {
    const paleta = corDoEspaco(espaco.cor);
    const ocupacao = ocupacaoDoEspaco(
        espaco,
        data,
        reservas.map((reserva) => ({
            inicio: minutosDoHorario(reserva.inicio),
            fim: minutosDoHorario(reserva.fim),
        })),
    );

    return (
        <div className="grid min-h-[72px] grid-cols-[200px_1fr] border-b last:border-b-0">
            <div className="flex flex-col justify-center gap-0.5 px-4 py-3 leading-tight">
                <span className="flex items-center gap-2 font-semibold">
                    <span
                        aria-hidden="true"
                        className="inline-block size-2.5 shrink-0 rounded-[3px]"
                        style={{ background: paleta.forte }}
                    />
                    {espaco.nome}
                </span>
                {espaco.capacidade !== null && (
                    <span className="text-xs text-muted-foreground">
                        {espaco.capacidade} pessoas
                    </span>
                )}
            </div>

            <div
                className="relative"
                style={{ backgroundImage: LINHAS_DAS_HORAS }}
            >
                {ocupacao.fechadas.map((faixa) => (
                    <div
                        key={`${faixa.tipo}-${faixa.inicio}`}
                        className="absolute inset-y-3 flex items-center overflow-hidden rounded-md px-3 text-xs text-muted-foreground"
                        style={{
                            left: `${percentual(faixa.inicio)}%`,
                            width: `${percentual(faixa.fim) - percentual(faixa.inicio)}%`,
                            backgroundImage: HACHURA,
                        }}
                    >
                        <span className="truncate rounded bg-background/90 px-1.5 py-0.5">
                            {faixa.texto}
                        </span>
                    </div>
                ))}

                {ocupacao.livres.map((faixa) => (
                    <button
                        key={`livre-${faixa.inicio}`}
                        type="button"
                        onClick={() =>
                            onReservar(data, horarioDoLivre(faixa), espaco.id)
                        }
                        className="absolute inset-y-3 flex items-center justify-center overflow-hidden rounded-md border border-dashed border-muted-foreground/60 px-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        style={{
                            left: `${percentual(faixa.inicio)}%`,
                            width: `${percentual(faixa.fim) - percentual(faixa.inicio)}%`,
                        }}
                    >
                        <span className="truncate">
                            Livre {intervaloDeMinutos(faixa)} · reservar
                        </span>
                    </button>
                ))}

                {reservas.map((reserva) => (
                    <button
                        key={reserva.id}
                        type="button"
                        aria-label={`${reserva.titulo}, ${reserva.inicio} a ${reserva.fim}`}
                        onClick={() => onEditarReserva(reserva)}
                        className="absolute inset-y-2.5 flex flex-col justify-center overflow-hidden rounded-lg border-l-[3px] px-2.5 py-1 text-left text-xs leading-tight transition-[filter] hover:brightness-95"
                        style={{
                            left: `${percentual(minutosDoHorario(reserva.inicio))}%`,
                            width: `${percentual(minutosDoHorario(reserva.fim)) - percentual(minutosDoHorario(reserva.inicio))}%`,
                            background: paleta.fundo,
                            borderLeftColor: paleta.forte,
                            color: paleta.texto,
                        }}
                    >
                        <span className="truncate text-[13px] font-semibold">
                            {reserva.titulo}
                        </span>
                        <span className="truncate">
                            {reserva.inicio}–{reserva.fim}
                            {reserva.grupo_nome
                                ? ` · ${reserva.grupo_nome}`
                                : ''}
                        </span>
                    </button>
                ))}

                {agoraNaFaixa !== null && (
                    <span
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-y-0 w-0.5 bg-destructive"
                        style={{ left: `${agoraNaFaixa}%` }}
                    />
                )}
            </div>
        </div>
    );
}

function LinhaDeSolicitacao({
    solicitacao,
    espacos,
    reservas,
}: {
    solicitacao: SolicitacaoDoCalendario;
    espacos: EspacoDoCalendario[];
    reservas: ReservaDoCalendario[];
}) {
    const espaco = espacos.find(
        (candidato) => candidato.id === solicitacao.espaco_id,
    );
    const estado = estadoDaSolicitacao(solicitacao, espacos, reservas);
    const contato = linkDoWhatsApp(solicitacao.telefone);

    return (
        <li className="flex flex-wrap items-center gap-3 px-4 py-3">
            <div className="flex min-w-0 flex-1 basis-64 flex-col leading-tight">
                <span className="font-semibold">{solicitacao.titulo}</span>
                <span className="text-sm text-muted-foreground">
                    {espaco?.nome ?? 'Sem espaço escolhido'} ·{' '}
                    {formatarDiaCurto(solicitacao.data)}, {solicitacao.inicio} –{' '}
                    {solicitacao.fim} · pedido por {solicitacao.nome}
                </span>
            </div>

            <SeloDeConflito estado={estado} />

            {contato !== null ? (
                <a
                    href={contato}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={cn(
                        buttonVariants({ variant: 'outline' }),
                        'h-11 gap-2 border-sucesso/40 bg-sucesso-fundo text-sucesso hover:bg-sucesso-fundo',
                    )}
                >
                    <MessageCircle aria-hidden="true" className="size-4" />
                    Entrar em contato
                    <span className="sr-only">
                        pelo WhatsApp (abre em nova aba)
                    </span>
                </a>
            ) : (
                <span className="text-sm text-muted-foreground">
                    Telefone inválido
                </span>
            )}

            <Link
                href={rotaDeSolicitacoes()}
                className={cn(
                    buttonVariants({ variant: 'outline' }),
                    'h-11 text-destructive',
                )}
            >
                Recusar
            </Link>

            {/* Aprovar e Resolver só levam à página de Solicitações: a ação de aprovar é da próxima etapa. */}
            <Link
                href={rotaDeSolicitacoes()}
                className={cn(
                    buttonVariants({
                        variant:
                            estado.situacao === 'conflito'
                                ? 'secondary'
                                : 'default',
                    }),
                    'h-11',
                )}
            >
                {estado.situacao === 'conflito' ? 'Resolver' : 'Aprovar'}
            </Link>
        </li>
    );
}

function SeloDeConflito({ estado }: { estado: EstadoDaSolicitacao }) {
    if (estado.situacao === 'sem_espaco') {
        return (
            <span className="rounded-full bg-secondary px-3 py-1 text-xs font-semibold text-muted-foreground">
                Escolha um espaço
            </span>
        );
    }

    if (estado.situacao === 'sem_conflito') {
        return (
            <span className="rounded-full bg-sucesso-fundo px-3 py-1 text-xs font-semibold text-sucesso">
                Sem conflito
            </span>
        );
    }

    return (
        <span
            className="max-w-xs truncate rounded-full bg-destructive/10 px-3 py-1 text-xs font-semibold text-destructive"
            title={estado.motivos.join(' ')}
        >
            Conflito: {estado.motivos[0]}
        </span>
    );
}
