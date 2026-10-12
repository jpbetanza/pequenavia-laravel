import { createContext, useContext, useState, type ReactNode } from 'react';

const CHAVE_ABERTO = 'assistente-aberto';

type ValorDoAssistente = {
    aberto: boolean;
    alternar: () => void;
    fechar: () => void;
};

const ContextoDoAssistente = createContext<ValorDoAssistente | null>(null);

/** Lembra se o painel estava aberto. Sem localStorage (janela privada, dados bloqueados), começa fechado. */
function lerAberto(): boolean {
    try {
        return window.localStorage.getItem(CHAVE_ABERTO) === '1';
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

    function definir(novo: boolean) {
        setAberto(novo);
        gravarAberto(novo);
    }

    return (
        <ContextoDoAssistente.Provider
            value={{
                aberto,
                alternar: () => definir(!aberto),
                fechar: () => definir(false),
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
