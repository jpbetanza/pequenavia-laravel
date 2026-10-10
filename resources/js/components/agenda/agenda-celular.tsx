import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { index as rotaDeSolicitacoes } from '@/routes/agendamentos/solicitacoes';
import { corDoEspaco } from '@/lib/agenda/cores';
import { diaDaSemana, diasDaSemana, horaDe } from '@/lib/agenda/datas';
import {
    espacosLivresAgora,
    posicaoDoAgoraNaLista,
    reservasDoDia,
} from '@/lib/agenda/agenda-do-celular';
import { cn } from '@/lib/utils';
import type {
    EspacoDoCalendario,
    ReservaDoCalendario,
    SolicitacaoDoCalendario,
} from '@/types/agendamentos';

type Props = {
    /** Dia selecionado, "YYYY-MM-DD". As abas mostram a semana dele. */
    data: string;
    hoje: string;
    /** "agora" ISO no fuso da agenda. */
    agora: string;
    espacos: EspacoDoCalendario[];
    reservas: ReservaDoCalendario[];
    solicitacoes: SolicitacaoDoCalendario[];
    onSelecionarDia: (dia: string) => void;
    onNovaReserva: (dia: string) => void;
    onEditarReserva: (reserva: ReservaDoCalendario) => void;
};

const SIGLAS = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB'];
const NOMES = [
    'Domingo',
    'Segunda',
    'Terça',
    'Quarta',
    'Quinta',
    'Sexta',
    'Sábado',
];

/** Agenda do dia para o celular (abaixo de md). A grade da semana e a visão do dia ficam para o computador. */
export default function AgendaCelular({
    data,
    hoje,
    agora,
    espacos,
    reservas,
    solicitacoes,
    onSelecionarDia,
    onNovaReserva,
    onEditarReserva,
}: Props) {
    const dias = diasDaSemana(data);
    const ehHoje = data === hoje;
    const hora = horaDe(agora);
    const doDia = reservasDoDia(reservas, data);
    const posicaoAgora = posicaoDoAgoraNaLista(doDia, ehHoje, hora);
    const livres = ehHoje
        ? espacosLivresAgora(espacos, reservas, data, hora)
        : [];
    const pendentes = solicitacoes.filter(
        (solicitacao) => solicitacao.data === data,
    );

    const itens: ReactNode[] = [];

    doDia.forEach((reserva, indice) => {
        if (posicaoAgora === indice) {
            itens.push(<MarcadorAgora key="agora" hora={hora} />);
        }

        itens.push(
            <li key={reserva.id}>
                <CartaoDaReserva
                    reserva={reserva}
                    espacos={espacos}
                    onEditar={onEditarReserva}
                />
            </li>,
        );
    });

    if (posicaoAgora !== null && posicaoAgora === doDia.length) {
        itens.push(<MarcadorAgora key="agora" hora={hora} />);
    }

    pendentes.forEach((solicitacao) => {
        itens.push(
            <li key={`solicitacao-${solicitacao.id}`}>
                <Link
                    href={rotaDeSolicitacoes()}
                    className="flex items-center gap-3 rounded-2xl border border-dashed border-primary/50 bg-aviso-fundo p-4 text-foreground"
                >
                    <span className="flex min-w-0 flex-1 flex-col">
                        <span className="text-sm text-muted-foreground">
                            {solicitacao.inicio} – {solicitacao.fim}
                        </span>
                        <span className="font-semibold">
                            {solicitacao.titulo}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            Solicitação de {solicitacao.nome}
                        </span>
                    </span>
                    <span className="text-sm font-semibold text-primary">
                        Revisar ›
                    </span>
                </Link>
            </li>,
        );
    });

    return (
        <div className="flex flex-col gap-4 p-4 pb-28 md:hidden">
            <div
                role="tablist"
                aria-label="Dias da semana"
                className="grid grid-cols-7 gap-1 text-center"
            >
                {dias.map((dia) => {
                    const ativo = dia === data;

                    return (
                        <button
                            key={dia}
                            type="button"
                            role="tab"
                            aria-selected={ativo}
                            aria-label={`${NOMES[diaDaSemana(dia)]}, dia ${Number(dia.slice(8))}`}
                            onClick={() => onSelecionarDia(dia)}
                            className={cn(
                                'flex min-h-[52px] flex-col items-center justify-center rounded-xl leading-tight transition-colors',
                                ativo
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-secondary',
                            )}
                        >
                            <span className="text-[11px]">
                                {SIGLAS[diaDaSemana(dia)]}
                            </span>
                            <span
                                className={cn(
                                    'text-base',
                                    ativo ? 'font-bold' : 'font-semibold',
                                )}
                            >
                                {Number(dia.slice(8))}
                            </span>
                        </button>
                    );
                })}
            </div>

            <h2 className="text-[13px] font-semibold tracking-wider text-muted-foreground uppercase">
                {NOMES[diaDaSemana(data)]} ·{' '}
                {doDia.length === 1 ? '1 reserva' : `${doDia.length} reservas`}
            </h2>

            {itens.length === 0 ? (
                <p className="rounded-2xl border bg-card p-4 text-sm text-muted-foreground">
                    Nenhuma reserva neste dia.
                </p>
            ) : (
                <ol className="flex flex-col gap-3">{itens}</ol>
            )}

            {ehHoje && (
                <section
                    aria-label="Livres agora"
                    className="flex flex-col gap-1.5 rounded-2xl bg-secondary p-4"
                >
                    <h2 className="font-semibold">Livres agora</h2>
                    <p className="text-sm text-muted-foreground">
                        {livres.length === 0
                            ? 'Nenhum espaço livre neste momento.'
                            : livres.map((espaco) => espaco.nome).join(' · ')}
                    </p>
                </section>
            )}

            <Button
                type="button"
                aria-label="Nova reserva"
                className="fixed right-5 bottom-6 z-20 size-16 rounded-full shadow-lg md:hidden"
                onClick={() => onNovaReserva(data)}
            >
                <Plus aria-hidden="true" className="size-7" />
            </Button>
        </div>
    );
}

function MarcadorAgora({ hora }: { hora: string }) {
    return (
        <li className="flex items-center gap-2 text-xs font-semibold text-destructive">
            <span
                aria-hidden="true"
                className="size-2 shrink-0 rounded-full bg-destructive"
            />
            Agora, {hora}
            <span aria-hidden="true" className="h-0.5 flex-1 bg-destructive" />
        </li>
    );
}

function CartaoDaReserva({
    reserva,
    espacos,
    onEditar,
}: {
    reserva: ReservaDoCalendario;
    espacos: EspacoDoCalendario[];
    onEditar: (reserva: ReservaDoCalendario) => void;
}) {
    const espaco = espacos.find(
        (candidato) => candidato.id === reserva.espaco_id,
    );
    const paleta = corDoEspaco(espaco?.cor ?? 'azul');

    return (
        <button
            type="button"
            onClick={() => onEditar(reserva)}
            className="flex min-h-11 w-full gap-3 rounded-2xl border bg-card p-4 text-left"
        >
            <span
                className="w-1 shrink-0 rounded-full"
                style={{ background: paleta.forte }}
                aria-hidden="true"
            />
            <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="text-[13px] text-muted-foreground">
                    {reserva.inicio} – {reserva.fim}
                </span>
                <span className="text-base font-semibold">
                    {reserva.titulo}
                </span>
                <span className="text-sm text-muted-foreground">
                    {espaco?.nome ?? 'Sem espaço'}
                    {reserva.grupo_nome ? ` · ${reserva.grupo_nome}` : ''}
                </span>
            </span>
        </button>
    );
}
