import * as DialogPrimitive from '@radix-ui/react-dialog';
import { usePage } from '@inertiajs/react';
import { Bell, ChevronLeft, ChevronRight, Sparkles, X } from 'lucide-react';
import { useRef, useState, type KeyboardEvent, type ReactNode } from 'react';
import { useAssistente } from '@/hooks/use-assistente';
import { aplicarAoRoteiro } from '@/lib/primeiros-passos/acoes';
import {
    cardDoDeslize,
    navegar,
    type Direcao,
} from '@/lib/primeiros-passos/carrossel';
import { PEDIDO_DO_ROTEIRO } from '@/lib/primeiros-passos/roteiro';
import { cn } from '@/lib/utils';

const TOTAL = 3;

/** Ilustração do card 1: um dia da agenda e certificados prontos. */
function IlustracaoAgenda() {
    return (
        <div
            aria-hidden="true"
            className="flex h-56 flex-col gap-2.5 rounded-[20px] bg-foreground p-5 text-background sm:h-60"
        >
            <span className="font-serif text-[22px] font-semibold">
                Quinta, 22
            </span>
            <span className="flex items-center gap-2.5 rounded-xl bg-background/10 px-3 py-2.5">
                <i className="h-7 w-1 rounded bg-dourado-claro" />
                <span className="leading-tight">
                    <b>Catequese</b>
                    <br />
                    <span className="text-background/70">Sala 2 · 15h–17h</span>
                </span>
            </span>
            <span className="flex items-center gap-2.5 rounded-xl bg-background/10 px-3 py-2.5">
                <i className="h-7 w-1 rounded bg-primary-foreground/60" />
                <span className="leading-tight">
                    <b>Ensaio do coral</b>
                    <br />
                    <span className="text-background/70">
                        Salão paroquial · 19h–21h
                    </span>
                </span>
            </span>
            <span className="flex items-center gap-2.5 rounded-xl border border-dashed border-background/30 px-3 py-2.5 text-background/70">
                <span className="flex size-5 items-center justify-center rounded-md bg-background/10 text-xs font-semibold text-dourado-claro">
                    C
                </span>
                Certificado de batismo · 12 prontos
            </span>
        </div>
    );
}

/** Ilustração do card 2: o aviso no celular e o pedido com Aprovar e Recusar. */
function IlustracaoPedidos() {
    return (
        <div
            aria-hidden="true"
            className="relative h-56 rounded-[20px] bg-muted sm:h-60"
        >
            <span className="absolute inset-x-4 top-4 flex items-center gap-2.5 rounded-2xl bg-background px-3 py-2.5 shadow-[0_6px_18px_rgba(34,26,23,0.12)]">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-[9px] bg-primary text-primary-foreground">
                    <Bell className="size-4" />
                </span>
                <span className="min-w-0 leading-tight">
                    <b>Nova solicitação: Adoração jovem</b>
                    <br />
                    <span className="text-[13px] text-muted-foreground">
                        Capela · qui 22, 20h–21h
                    </span>
                </span>
            </span>
            <span className="absolute inset-x-4 bottom-4 flex flex-col gap-2.5 rounded-2xl border bg-card p-3.5">
                <span className="flex justify-between gap-2">
                    <b>Pastoral da Juventude</b>
                    <span className="rounded-full bg-sucesso-fundo px-2 py-0.5 text-xs font-semibold text-sucesso">
                        Sem conflito
                    </span>
                </span>
                <span className="flex gap-2 font-semibold">
                    <span className="flex min-h-9 flex-1 items-center justify-center rounded-[10px] bg-primary text-primary-foreground">
                        Aprovar
                    </span>
                    <span className="flex min-h-9 flex-1 items-center justify-center rounded-[10px] border">
                        Recusar
                    </span>
                </span>
            </span>
        </div>
    );
}

/** Ilustração do card 3: a conversa que vira cadastro. */
function IlustracaoAssistente() {
    return (
        <div
            aria-hidden="true"
            className="flex h-52 flex-col gap-2.5 rounded-[20px] bg-foreground p-4 text-background sm:h-56"
        >
            <span className="max-w-[82%] self-end rounded-[14px] rounded-br-sm bg-primary px-3 py-2 text-primary-foreground">
                Temos o salão para 120 pessoas e a capela para 80
            </span>
            <span className="text-background/80">
                Entendi 2 espaços. Confira e confirme:
            </span>
            <span className="flex items-center gap-2.5 rounded-xl bg-background px-3 py-2.5 text-foreground">
                <b className="flex-1">Novo espaço: Salão paroquial</b>
                <span className="rounded-full bg-aviso-fundo px-2 py-0.5 text-xs font-semibold text-aviso">
                    Aguardando
                </span>
            </span>
            <span className="flex min-h-8 items-center self-start rounded-[10px] bg-dourado-claro px-3 font-semibold text-foreground">
                Confirmar todas (2)
            </span>
        </div>
    );
}

type Card = { titulo: string; texto: string; ilustracao: ReactNode };

const CARDS: Card[] = [
    {
        titulo: 'Boas-vindas ao PequenaVia',
        texto: 'A agenda das salas, os pedidos dos grupos e os certificados da paróquia, num lugar só. Sem planilha perdida, sem recado no papel.',
        ilustracao: <IlustracaoAgenda />,
    },
    {
        titulo: 'Os grupos pedem, você decide',
        texto: 'Mande um link aos coordenadores: eles pedem a sala sem precisar de conta. O pedido chega com os conflitos já conferidos, e o celular avisa na hora.',
        ilustracao: <IlustracaoPedidos />,
    },
    {
        titulo: 'Deixe o assistente montar tudo',
        texto: 'Conte como são as salas do seu jeito, ou mande uma planilha. Ele prepara os cadastros e você só confirma. Uns dez minutos e a agenda está pronta.',
        ilustracao: <IlustracaoAssistente />,
    },
];

/**
 * Modal do primeiro acesso: três cards navegáveis na horizontal (setas, pontos, teclado e deslize), o último
 * convidando a configurar a agenda com o assistente. Fechar de qualquer jeito marca como visto: não volta.
 */
export function ModalDeBoasVindas() {
    const { assistente } = usePage().props;
    const { perguntar } = useAssistente();
    const [aberto, setAberto] = useState(true);
    const [card, setCard] = useState(0);
    const [direcao, setDirecao] = useState<Direcao>('avancar');
    const toque = useRef<{ x: number; y: number } | null>(null);
    const atual = CARDS[card];
    const ultimo = card === TOTAL - 1;

    function ir(alvo: number) {
        const destino = navegar(card, alvo, TOTAL);

        if (destino !== null) {
            setCard(destino.card);
            setDirecao(destino.direcao);
        }
    }

    function fechar() {
        setAberto(false);
        aplicarAoRoteiro('modal_visto');
    }

    function configurarComAssistente() {
        fechar();
        perguntar(PEDIDO_DO_ROTEIRO);
    }

    function aoTeclar(evento: KeyboardEvent<HTMLDivElement>) {
        if (evento.key === 'ArrowRight') {
            evento.preventDefault();
            ir(card + 1);
        } else if (evento.key === 'ArrowLeft') {
            evento.preventDefault();
            ir(card - 1);
        }
    }

    return (
        <DialogPrimitive.Root
            open={aberto}
            onOpenChange={(novo) => {
                if (!novo) {
                    fechar();
                }
            }}
        >
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 animate-[boas-vindas-fundo_0.5s_ease_both] bg-foreground/55 motion-reduce:animate-none" />
                <DialogPrimitive.Content
                    aria-roledescription="carrossel"
                    onKeyDown={aoTeclar}
                    onTouchStart={(evento) => {
                        const ponto = evento.touches[0];
                        toque.current = { x: ponto.clientX, y: ponto.clientY };
                    }}
                    onTouchEnd={(evento) => {
                        const inicio = toque.current;
                        const ponto = evento.changedTouches[0];
                        toque.current = null;

                        if (inicio !== null) {
                            ir(
                                cardDoDeslize(
                                    card,
                                    ponto.clientX - inicio.x,
                                    ponto.clientY - inicio.y,
                                ),
                            );
                        }
                    }}
                    className="fixed top-1/2 left-1/2 z-50 flex h-[min(640px,calc(100dvh-2rem))] w-[min(420px,calc(100vw-2rem))] -translate-x-1/2 -translate-y-1/2 animate-[boas-vindas-subir_0.7s_cubic-bezier(0.16,0.84,0.3,1)_0.12s_both] flex-col overflow-hidden rounded-[28px] bg-card text-card-foreground shadow-[0_30px_80px_rgba(34,26,23,0.35)] outline-none motion-reduce:animate-none"
                >
                    <div className="flex items-center justify-between pt-4 pr-4 pl-6">
                        <span
                            aria-live="polite"
                            className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                        >
                            {card + 1} de {TOTAL}
                        </span>
                        <DialogPrimitive.Close
                            aria-label="Fechar boas-vindas"
                            className="flex size-11 items-center justify-center rounded-xl text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            <X aria-hidden="true" className="size-5" />
                        </DialogPrimitive.Close>
                    </div>

                    <div
                        key={card}
                        role="group"
                        aria-roledescription="card"
                        aria-label={`${card + 1} de ${TOTAL}`}
                        className={cn(
                            'flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-6 pt-2 motion-reduce:animate-none sm:px-8',
                            direcao === 'avancar'
                                ? 'animate-[boas-vindas-da-direita_0.38s_cubic-bezier(0.2,0.8,0.2,1)_both]'
                                : 'animate-[boas-vindas-da-esquerda_0.38s_cubic-bezier(0.2,0.8,0.2,1)_both]',
                        )}
                    >
                        {atual.ilustracao}
                        <div className="flex flex-col gap-2.5">
                            <DialogPrimitive.Title className="font-serif text-[34px] leading-[1.02] font-semibold sm:text-4xl">
                                {atual.titulo}
                            </DialogPrimitive.Title>
                            <DialogPrimitive.Description className="text-base text-foreground/80">
                                {atual.texto}
                            </DialogPrimitive.Description>
                        </div>

                        {ultimo && (
                            <div className="flex flex-col gap-1.5">
                                {assistente?.disponivel ? (
                                    <button
                                        type="button"
                                        onClick={configurarComAssistente}
                                        className="flex min-h-12 items-center justify-center gap-2.5 rounded-[14px] bg-primary text-[15px] font-semibold text-primary-foreground hover:bg-primary/90"
                                    >
                                        <Sparkles
                                            aria-hidden="true"
                                            className="size-[18px]"
                                        />
                                        Configurar com o assistente
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={fechar}
                                        className="flex min-h-12 items-center justify-center rounded-[14px] bg-primary text-[15px] font-semibold text-primary-foreground hover:bg-primary/90"
                                    >
                                        Ver os primeiros passos
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={fechar}
                                    className="min-h-11 font-semibold text-muted-foreground hover:text-foreground"
                                >
                                    Explorar por conta própria
                                </button>
                            </div>
                        )}
                    </div>

                    <div className="flex items-center justify-between gap-3 px-5 pt-3.5 pb-5">
                        {card > 0 ? (
                            <button
                                type="button"
                                onClick={() => ir(card - 1)}
                                aria-label="Card anterior"
                                className="flex size-11 items-center justify-center rounded-full border bg-background transition-colors hover:bg-muted"
                            >
                                <ChevronLeft
                                    aria-hidden="true"
                                    className="size-5"
                                />
                            </button>
                        ) : (
                            <span aria-hidden="true" className="size-11" />
                        )}

                        <div
                            role="tablist"
                            aria-label="Cards de boas-vindas"
                            className="flex items-center gap-0.5"
                        >
                            {CARDS.map((item, indice) => (
                                <button
                                    key={item.titulo}
                                    type="button"
                                    role="tab"
                                    aria-selected={indice === card}
                                    aria-label={`Ir para o card ${indice + 1} de ${TOTAL}`}
                                    onClick={() => ir(indice)}
                                    className="flex h-11 w-8 items-center justify-center"
                                >
                                    <span
                                        className={cn(
                                            'block h-2 rounded-full transition-[width,background-color] duration-350 ease-[cubic-bezier(0.2,0.8,0.2,1)] motion-reduce:transition-none',
                                            indice === card
                                                ? 'w-7 bg-primary'
                                                : 'w-2 bg-border',
                                        )}
                                    />
                                </button>
                            ))}
                        </div>

                        {!ultimo ? (
                            <button
                                type="button"
                                onClick={() => ir(card + 1)}
                                aria-label="Próximo card"
                                className="flex size-11 items-center justify-center rounded-full bg-foreground text-background transition-colors hover:bg-foreground/85"
                            >
                                <ChevronRight
                                    aria-hidden="true"
                                    className="size-5"
                                />
                            </button>
                        ) : (
                            <span aria-hidden="true" className="size-11" />
                        )}
                    </div>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
