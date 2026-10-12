import { createContext, useContext, useState, type ReactNode } from 'react';
import {
    abertoAoCarregar,
    CONSULTA_CELULAR,
    deveGravarPreferencia,
} from '@/lib/assistente/painel';

const CHAVE_ABERTO = 'assistente-aberto';

type ValorDoAssistente = {
    aberto: boolean;
    alternar: () => void;
    fechar: () => void;
    /** Pergunta enviada de fora do painel (ex.: Início), ainda não entregue à conversa. */
    perguntaPendente: string | null;
    /** Abre o painel e manda a pergunta numa conversa nova. */
    perguntar: (texto: string) => void;
    /** A conversa avisa que pegou a pergunta. */
    consumirPergunta: () => void;
};

const ContextoDoAssistente = createContext<ValorDoAssistente | null>(null);

function ehCelular(): boolean {
    try {
        return window.matchMedia(CONSULTA_CELULAR).matches;
    } catch {
        return false;
    }
}

/** Lembra se o painel estava aberto. Sem localStorage (janela privada, dados bloqueados), começa fechado. */
function lerAberto(): boolean {
    try {
        return abertoAoCarregar(
            window.localStorage.getItem(CHAVE_ABERTO),
            ehCelular(),
        );
    } catch {
        return false;
    }
}

function gravarAberto(aberto: boolean): void {
    try {
        window.localStorage.setItem(CHAVE_ABERTO, aberto ? '1' : '0');
    } catch {
        // Sem armazenamento, o painel só não lembra a escolha.
    }
}

export function ProvedorDoAssistente({ children }: { children: ReactNode }) {
    const [aberto, setAberto] = useState(lerAberto);
    const [perguntaPendente, setPerguntaPendente] = useState<string | null>(
        null,
    );

    function definir(novo: boolean) {
        setAberto(novo);

        if (deveGravarPreferencia(ehCelular())) {
            gravarAberto(novo);
        }
    }

    return (
        <ContextoDoAssistente.Provider
            value={{
                aberto,
                alternar: () => definir(!aberto),
                fechar: () => definir(false),
                perguntaPendente,
                perguntar: (texto: string) => {
                    const limpa = texto.trim();

                    if (limpa === '') {
                        return;
                    }

                    setPerguntaPendente(limpa);
                    definir(true);
                },
                consumirPergunta: () => setPerguntaPendente(null),
            }}
        >
            {children}
        </ContextoDoAssistente.Provider>
    );
}

export function useAssistente(): ValorDoAssistente {
    const valor = useContext(ContextoDoAssistente);

    if (valor === null) {
        throw new Error(
            'useAssistente precisa estar dentro de ProvedorDoAssistente.',
        );
    }

    return valor;
}
