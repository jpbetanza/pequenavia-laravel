import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useAssistente } from '@/hooks/use-assistente';
import { useIsMobile } from '@/hooks/use-mobile';
import { ConversaDoAssistente } from './conversa-do-assistente';
import { ListaDeConversas } from './lista-de-conversas';

const CHAVE_CONVERSA = 'assistente-conversa';

/** Conversa aberta por último neste navegador. Sem localStorage, começa numa conversa nova. */
function lerConversaSalva(): string | null {
    try {
        return window.localStorage.getItem(CHAVE_CONVERSA);
    } catch {
        return null;
    }
}

function gravarConversaSalva(id: string | null): void {
    try {
        if (id === null) {
            window.localStorage.removeItem(CHAVE_CONVERSA);
        } else {
            window.localStorage.setItem(CHAVE_CONVERSA, id);
        }
    } catch {
        // Sem armazenamento, a conversa só não é lembrada na próxima visita.
    }
}

/** Troca entre a conversa e a lista. A `sessao` remonta a conversa ao abrir outra ou uma nova. */
function ConteudoDoAssistente({ aoFechar }: { aoFechar: () => void }) {
    const [vista, setVista] = useState<'conversa' | 'lista'>('conversa');
    const [conversaId, setConversaId] = useState<string | null>(
        lerConversaSalva,
    );
    const [sessao, setSessao] = useState(0);

    function gravar(id: string | null) {
        setConversaId(id);
        gravarConversaSalva(id);
    }

    function abrirConversa(id: string | null) {
        gravar(id);
        setSessao((atual) => atual + 1);
        setVista('conversa');
    }

    if (vista === 'lista') {
        return (
            <ListaDeConversas
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
        );
    }

    return (
        <ConversaDoAssistente
            key={sessao}
            conversaIdInicial={conversaId}
            aoConversaCriada={gravar}
            aoVerConversas={() => setVista('lista')}
            aoNovaConversa={() => abrirConversa(null)}
            aoFechar={aoFechar}
        />
    );
}

/** Painel lateral no computador e folha de tela cheia no celular. Só aparece com o assistente disponível e aberto. */
export function PainelDoAssistente() {
    const { aberto, fechar } = useAssistente();
    const celular = useIsMobile();
    const { assistente } = usePage().props;

    if (!aberto || !assistente?.disponivel) {
        return null;
    }

    if (celular) {
        return (
            <Sheet
                open
                onOpenChange={(novo) => {
                    if (!novo) {
                        fechar();
                    }
                }}
            >
                <SheetContent
                    side="bottom"
                    id="painel-assistente"
                    className="h-svh max-h-none p-0 [&>button:last-child]:hidden"
                >
                    <SheetTitle className="sr-only">Assistente</SheetTitle>
                    <SheetDescription className="sr-only">
                        Converse com o assistente sobre a agenda e os
                        certificados.
                    </SheetDescription>
                    <ConteudoDoAssistente aoFechar={fechar} />
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <aside
            id="painel-assistente"
            aria-label="Assistente"
            className="sticky top-0 flex h-svh w-[420px] shrink-0 flex-col border-l bg-card print:hidden"
        >
            <ConteudoDoAssistente aoFechar={fechar} />
        </aside>
    );
}
