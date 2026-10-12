import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useAssistente } from '@/hooks/use-assistente';
import { useIsMobile } from '@/hooks/use-mobile';
import { chaveDaConversaSalva } from '@/lib/assistente/conversa-salva';
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
 * Conversa e lista ficam montadas o tempo todo; a inativa só some (`hidden`). Assim uma resposta em
 * andamento termina mesmo com o painel fechado ou na lista. A `sessao` remonta a conversa ao abrir outra ou uma nova.
 */
function ConteudoDoAssistente({ userId, aberto, aoFechar }: ConteudoProps) {
    const chave = chaveDaConversaSalva(userId);
    const [vista, setVista] = useState<'conversa' | 'lista'>('conversa');
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
    useEffect(
        () =>
            router.on('start', () => {
                if (celular) {
                    fechar();
                }
            }),
        [celular, fechar],
    );

    // Ao abrir no celular, o foco vai para o painel. Painel escondido não recebe foco.
    useEffect(() => {
        if (celular && aberto) {
            painelCelular.current?.focus();
        }
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
                    'sticky top-0 h-svh w-[420px] shrink-0 flex-col border-l bg-card print:hidden',
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
