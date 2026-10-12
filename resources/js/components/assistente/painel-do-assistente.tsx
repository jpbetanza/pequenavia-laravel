import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useAssistente } from '@/hooks/use-assistente';
import { useIsMobile } from '@/hooks/use-mobile';
import { chaveDaConversaSalva } from '@/lib/assistente/conversa-salva';
import { deveFecharAoNavegar } from '@/lib/assistente/painel';
import { cn } from '@/lib/utils';
import { ConversaDoAssistente } from './conversa-do-assistente';
import { ListaDeConversas } from './lista-de-conversas';

/** Conversa aberta por último neste navegador, por usuário. Sem localStorage, começa numa conversa nova. */
function lerConversaSalva(chave: string): string | null {
    try {
        return window.localStorage.getItem(chave);
    } catch {
        return null;
    }
}

function gravarConversaSalva(chave: string, id: string | null): void {
    try {
        if (id === null) {
            window.localStorage.removeItem(chave);
        } else {
            window.localStorage.setItem(chave, id);
        }
    } catch {
        // Sem armazenamento, a conversa só não é lembrada na próxima visita.
    }
}

type ConteudoProps = {
    userId: string;
    aberto: boolean;
    aoFechar: () => void;
};

/**
 * Torna inertes os irmãos de cada ancestral do painel, para o foco e os cliques não chegarem ao fundo. Devolve a função que desfaz.
 */
function isolarFundo(painel: HTMLElement): () => void {
    const inertes: HTMLElement[] = [];
    let atual: HTMLElement = painel;

    while (atual.parentElement !== null && atual !== document.body) {
        for (const irmao of Array.from(atual.parentElement.children)) {
            if (
                irmao !== atual &&
                irmao instanceof HTMLElement &&
                !irmao.inert
            ) {
                irmao.inert = true;
                inertes.push(irmao);
            }
        }

        atual = atual.parentElement;
    }

    return () => {
        for (const elemento of inertes) {
            elemento.inert = false;
        }
    };
}

/**
 * Conversa e lista ficam montadas o tempo todo; a inativa só some (`hidden`). Assim uma resposta em
 * andamento termina mesmo com o painel fechado ou na lista. A `sessao` remonta a conversa ao abrir outra ou uma nova.
 */
function ConteudoDoAssistente({ userId, aberto, aoFechar }: ConteudoProps) {
    const chave = chaveDaConversaSalva(userId);
    const [vista, setVista] = useState<'conversa' | 'lista'>('conversa');
    const [enviando, setEnviando] = useState(false);
    const [conversaId, setConversaId] = useState<string | null>(() =>
        lerConversaSalva(chave),
    );
    const [sessao, setSessao] = useState(0);

    function gravar(id: string | null) {
        setConversaId(id);
        gravarConversaSalva(chave, id);
    }

    function abrirConversa(id: string | null) {
        gravar(id);
        setSessao((atual) => atual + 1);
        setVista('conversa');
    }

    return (
        <>
            <div
                className={cn(
                    'min-h-0 flex-1 flex-col',
                    vista === 'conversa' ? 'flex' : 'hidden',
                )}
            >
                <ConversaDoAssistente
                    key={sessao}
                    conversaIdInicial={conversaId}
                    aoConversaCriada={gravar}
                    aoConversaPerdida={() => gravar(null)}
                    aoVerConversas={() => setVista('lista')}
                    aoNovaConversa={() => abrirConversa(null)}
                    aoFechar={aoFechar}
                    aoMudarEnviando={setEnviando}
                />
            </div>
            <div
                className={cn(
                    'min-h-0 flex-1 flex-col',
                    vista === 'lista' ? 'flex' : 'hidden',
                )}
            >
                <ListaDeConversas
                    visivel={vista === 'lista' && aberto}
                    conversaAtualId={conversaId}
                    enviando={enviando}
                    aoAbrir={abrirConversa}
                    aoNovaConversa={() => abrirConversa(null)}
                    aoVoltar={() => setVista('conversa')}
                    aoFechar={aoFechar}
                    aoExcluir={(id) => {
                        if (id === conversaId) {
                            abrirConversa(null);
                        }
                    }}
                />
            </div>
        </>
    );
}

/**
 * Painel lateral no computador e tela cheia no celular. Fica montado enquanto o assistente estiver
 * disponível; fechado, só fica escondido, para a resposta em andamento não ser cortada.
 */
export function PainelDoAssistente() {
    const { aberto, fechar } = useAssistente();
    const celular = useIsMobile();
    const { assistente, auth } = usePage().props;
    const painelCelular = useRef<HTMLDivElement>(null);
    const userId = auth.user.id;

    // No celular, a tela cheia taparia o destino de uma navegação (um link de cartão): fecha ao sair da página.
    // Um reload da própria página (ex.: depois de confirmar um cartão) não fecha.
    useEffect(
        () =>
            router.on('start', (evento) => {
                if (
                    celular &&
                    deveFecharAoNavegar(
                        window.location.href,
                        evento.detail.visit.url.href,
                    )
                ) {
                    fechar();
                }
            }),
        [celular, fechar],
    );

    // No celular o painel é um diálogo modal: o foco entra nele, o fundo fica inerte e a rolagem trava.
    // Ao fechar, o foco volta para o botão "Assistente", que abriu o painel.
    useEffect(() => {
        if (!celular || !aberto) {
            return;
        }

        const painel = painelCelular.current;
        const rolagemAnterior = document.body.style.overflow;
        const soltarFundo = painel === null ? () => {} : isolarFundo(painel);

        document.body.style.overflow = 'hidden';
        painel?.focus();

        return () => {
            soltarFundo();
            document.body.style.overflow = rolagemAnterior;
            document
                .querySelector<HTMLElement>(
                    '[aria-controls="painel-assistente"]',
                )
                ?.focus();
        };
    }, [celular, aberto]);

    if (!assistente?.disponivel) {
        return null;
    }

    if (!celular) {
        return (
            <aside
                id="painel-assistente"
                aria-label="Assistente"
                className={cn(
                    'sticky top-0 h-svh w-[420px] shrink-0 flex-col self-start border-l bg-card print:hidden',
                    aberto ? 'flex' : 'hidden',
                )}
            >
                <ConteudoDoAssistente
                    userId={userId}
                    aberto={aberto}
                    aoFechar={fechar}
                />
            </aside>
        );
    }

    return (
        <div
            id="painel-assistente"
            ref={painelCelular}
            role="dialog"
            aria-modal="true"
            aria-label="Assistente"
            tabIndex={-1}
            onKeyDown={(evento) => {
                // Escape já tratado por outra camada (ex.: o diálogo de excluir conversa) não fecha o painel também.
                if (evento.defaultPrevented) {
                    return;
                }

                if (evento.key === 'Escape') {
                    fechar();
                }
            }}
            className={cn(
                'fixed inset-0 z-50 h-svh flex-col bg-background outline-none',
                aberto ? 'flex' : 'hidden',
            )}
        >
            <ConteudoDoAssistente
                userId={userId}
                aberto={aberto}
                aoFechar={fechar}
            />
        </div>
    );
}
