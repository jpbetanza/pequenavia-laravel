import { ArrowUp, Sparkles } from 'lucide-react';
import { type CorDoEspaco, corDoEspaco } from '@/lib/agenda/cores';

function BalaoDoUsuario({ children }: { children: string }) {
    return (
        <p className="max-w-[80%] self-end rounded-[14px_14px_4px_14px] bg-primary px-3.5 py-2.5 text-primary-foreground">
            {children}
        </p>
    );
}

function Quadrado({ cor }: { cor: CorDoEspaco }) {
    return (
        <span
            aria-hidden="true"
            className="size-2.5 shrink-0 rounded-[3px]"
            style={{ background: corDoEspaco(cor).forte }}
        />
    );
}

function IconeDoAssistente({ className }: { className: string }) {
    return (
        <span
            className={`flex items-center justify-center rounded-full bg-primary/10 text-primary ${className}`}
        >
            <Sparkles className="size-3.5" aria-hidden="true" />
        </span>
    );
}

function BotoesDeDecisao({
    descartar,
    confirmar,
}: {
    descartar: string;
    confirmar: string;
}) {
    return (
        <div className="flex gap-2">
            <span className="flex flex-1 items-center justify-center rounded-[10px] border border-input py-2.5 text-[13px] font-semibold">
                {descartar}
            </span>
            <span className="flex flex-1 items-center justify-center rounded-[10px] bg-primary py-2.5 text-[13px] font-semibold text-primary-foreground">
                {confirmar}
            </span>
        </div>
    );
}

export function ExemploDoAssistente() {
    const paleta = corDoEspaco('azul');

    return (
        <figure
            aria-label="Exemplo: conversa com o assistente"
            className="m-0 ml-auto flex w-full max-w-[340px] flex-col gap-2.5 rounded-[18px] border border-input bg-card p-4 text-sm shadow-[0_34px_70px_-30px_rgba(40,20,15,0.55)] md:absolute md:right-0 md:bottom-0 md:w-[340px] md:max-w-none"
        >
            <div className="flex items-center gap-2 text-[13px] font-semibold text-primary">
                <IconeDoAssistente className="size-[26px]" />
                Assistente
            </div>

            <BalaoDoUsuario>
                Tem sala livre sábado às 15h para uns 30 catequizandos?
            </BalaoDoUsuario>

            <p className="text-foreground">
                O Salão São José já está com a Crisma. A{' '}
                <b>Sala Santa Teresinha</b> fica livre das 16h às 18h e cabe 40
                pessoas. Quer que eu reserve?
            </p>

            <div className="flex flex-col gap-2 rounded-xl border border-border bg-background p-3">
                <span className="text-[11px] font-semibold tracking-[0.12em] text-dourado uppercase">
                    Nova reserva · aguardando você
                </span>
                <span className="flex items-center gap-2 font-semibold">
                    <span
                        aria-hidden="true"
                        className="size-2.5 rounded-[3px]"
                        style={{ background: paleta.forte }}
                    />
                    Sala Santa Teresinha
                </span>
                <span className="text-[13px] text-foreground/80">
                    Sáb, 17 out · 16h–18h · Catequese
                </span>
                <div className="mt-0.5">
                    <BotoesDeDecisao
                        descartar="Descartar"
                        confirmar="Confirmar"
                    />
                </div>
            </div>
        </figure>
    );
}

const CONSULTAS = [
    'O que tem amanhã?',
    'Solicitações pendentes',
    'Certificados emitidos este mês',
];

const PEDIDOS: {
    titulo: string;
    detalhe: string;
    cor: CorDoEspaco;
    selo: string;
    seloCor: 'aviso' | 'sucesso';
}[] = [
    {
        titulo: 'Encontro de noivos',
        detalhe: 'Salão · sáb 19h–21h',
        cor: 'bordo',
        selo: '2 conflitos',
        seloCor: 'aviso',
    },
    {
        titulo: 'Adoração jovem',
        detalhe: 'Capela · qui 20h–21h',
        cor: 'verde',
        selo: 'Livre',
        seloCor: 'sucesso',
    },
    {
        titulo: 'Reunião do dízimo',
        detalhe: 'Sala São Francisco · ter 19h',
        cor: 'roxo',
        selo: 'Livre',
        seloCor: 'sucesso',
    },
];

export function PainelDoAssistente() {
    return (
        <figure
            aria-label="Exemplo: painel do assistente"
            className="m-0 flex flex-col overflow-hidden rounded-[22px] bg-card text-sm text-foreground shadow-[0_40px_80px_-30px_rgba(0,0,0,0.6)]"
        >
            <div className="flex items-center justify-between border-b border-border px-5 py-4">
                <span className="flex items-center gap-2.5 font-semibold">
                    <IconeDoAssistente className="size-[30px]" />
                    Assistente
                </span>
                <span className="text-[13px] text-muted-foreground">
                    Conversas
                </span>
            </div>

            <div className="flex flex-col gap-3.5 p-5">
                <BalaoDoUsuario>Tem pedido esperando resposta?</BalaoDoUsuario>
                <p>Sim, 3 pedidos pendentes. Um deles tem conflito:</p>

                <div className="overflow-hidden rounded-[14px] border border-border">
                    {PEDIDOS.map((pedido, indice) => (
                        <div
                            key={pedido.titulo}
                            className={`flex items-center gap-3 px-3.5 py-3 ${
                                indice < PEDIDOS.length - 1
                                    ? 'border-b border-border/70'
                                    : ''
                            }`}
                        >
                            <Quadrado cor={pedido.cor} />
                            <span className="flex-1">
                                <b>{pedido.titulo}</b>
                                <br />
                                <span className="text-[13px] text-muted-foreground">
                                    {pedido.detalhe}
                                </span>
                            </span>
                            <span
                                className={`rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ${
                                    pedido.seloCor === 'aviso'
                                        ? 'bg-aviso-fundo text-aviso'
                                        : 'bg-sucesso-fundo text-sucesso'
                                }`}
                            >
                                {pedido.selo}
                            </span>
                        </div>
                    ))}
                </div>

                <BalaoDoUsuario>
                    Cadastra a sala nova do 2º andar, 25 lugares, com TV.
                </BalaoDoUsuario>

                <div className="flex flex-col gap-2.5 rounded-[14px] border border-border bg-background p-3.5">
                    <span className="text-[11px] font-semibold tracking-[0.12em] text-dourado uppercase">
                        Novo espaço · aguardando você
                    </span>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-3.5 gap-y-1 text-[13px]">
                        <dt className="text-muted-foreground">Nome</dt>
                        <dd className="font-semibold">Sala do 2º andar</dd>
                        <dt className="text-muted-foreground">Lugares</dt>
                        <dd>25</dd>
                        <dt className="text-muted-foreground">Recursos</dt>
                        <dd>TV</dd>
                    </dl>
                    <BotoesDeDecisao
                        descartar="Descartar"
                        confirmar="Confirmar"
                    />
                </div>
            </div>

            <div className="flex flex-col gap-2.5 border-t border-border px-5 pt-3.5 pb-5">
                <div className="flex flex-wrap gap-1.5">
                    {CONSULTAS.map((consulta) => (
                        <span
                            key={consulta}
                            className="rounded-full border border-border px-3 py-1.5 text-[13px]"
                        >
                            {consulta}
                        </span>
                    ))}
                </div>
                <div className="flex items-center gap-2.5 rounded-[14px] border border-input bg-background py-1.5 pr-1.5 pl-4 text-muted-foreground">
                    <span className="truncate">
                        Pergunte sobre a agenda ou os certificados…
                    </span>
                    <span
                        aria-hidden="true"
                        className="ml-auto flex size-[38px] shrink-0 items-center justify-center rounded-[10px] bg-primary text-primary-foreground"
                    >
                        <ArrowUp className="size-4" />
                    </span>
                </div>
            </div>
        </figure>
    );
}
