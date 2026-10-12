import { MessageCircle, TriangleAlert } from 'lucide-react';
import { type CorDoEspaco, corDoEspaco } from '@/lib/agenda/cores';

const COR_DO_SALAO: CorDoEspaco = 'bordo';

export function ExemploDePedido() {
    const paleta = corDoEspaco(COR_DO_SALAO);

    return (
        <figure
            aria-label="Exemplo: solicitação com conflito"
            className="m-0 flex flex-col gap-3.5"
        >
            <article className="flex flex-col gap-3.5 rounded-[18px] border border-border bg-card p-5 shadow-[0_24px_50px_-36px_rgba(60,30,20,0.5)] sm:p-[22px]">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex flex-col gap-0.5">
                        <span className="text-xs font-semibold tracking-[0.12em] text-dourado uppercase">
                            Pedido pelo link · há 2 horas
                        </span>
                        <span className="text-lg font-semibold">
                            Encontro de noivos
                        </span>
                        <span className="text-sm text-muted-foreground">
                            Marcos Lima · Pastoral Familiar
                        </span>
                    </div>
                    <span className="rounded-full bg-aviso-fundo px-2.5 py-1 text-xs font-semibold whitespace-nowrap text-aviso">
                        Pendente
                    </span>
                </div>

                <div className="flex flex-wrap gap-2 text-sm">
                    <span
                        className="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 font-semibold"
                        style={{
                            background: paleta.fundo,
                            color: paleta.texto,
                        }}
                    >
                        <span
                            aria-hidden="true"
                            className="size-2 rounded-full"
                            style={{ background: paleta.forte }}
                        />
                        Salão São José
                    </span>
                    <span className="rounded-lg bg-secondary px-2.5 py-1.5">
                        Sáb, 17 out · 19h–21h
                    </span>
                    <span className="rounded-lg bg-secondary px-2.5 py-1.5">
                        Projetor ×1
                    </span>
                </div>

                <ul className="flex flex-col gap-1.5 rounded-xl bg-aviso-fundo p-3.5 text-sm text-aviso">
                    <li className="flex gap-2">
                        <TriangleAlert
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            <b>Salão ocupado</b> · Ensaio do coral, 19h30–21h
                        </span>
                    </li>
                    <li className="flex gap-2">
                        <TriangleAlert
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            <b>Projetor ×1</b> · os 2 já estão reservados nesse
                            horário
                        </span>
                    </li>
                </ul>

                <p className="text-sm text-foreground/80">
                    Livre no mesmo horário: <b>Capela</b> (sem projetor) ou{' '}
                    <b>Salão São José</b> às 17h.
                </p>

                <div className="flex flex-wrap gap-2">
                    <span className="flex min-h-11 items-center gap-2 rounded-[10px] border border-input px-3.5 text-sm font-semibold">
                        <MessageCircle
                            className="size-4 text-sucesso"
                            aria-hidden="true"
                        />
                        Entrar em contato
                    </span>
                    <span className="flex-1" aria-hidden="true" />
                    <span className="flex min-h-11 items-center rounded-[10px] border border-input px-4 text-sm font-semibold">
                        Recusar
                    </span>
                    <span className="flex min-h-11 items-center rounded-[10px] bg-primary px-4.5 text-sm font-semibold text-primary-foreground">
                        Resolver
                    </span>
                </div>
            </article>
            <figcaption className="px-1.5 text-sm text-muted-foreground">
                Você ajusta o pedido no próprio cartão, troca a sala ou o
                horário, e aprova quando o conflito some.
            </figcaption>
        </figure>
    );
}
