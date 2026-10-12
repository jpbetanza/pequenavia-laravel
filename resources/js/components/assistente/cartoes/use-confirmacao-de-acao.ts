import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    confirmar,
    descartar,
} from '@/actions/App/Http/Controllers/Assistente/AcaoPropostaController';
import { acaoDoCorpoDeErro } from '@/lib/assistente/eventos';
import { ErroHttp, enviarJson, mensagemDoErro } from '@/lib/http';
import type { AcaoProposta } from '@/types/assistente';
import { rotuloDoDia } from './formatos';

export type OperacaoDeAcao = 'confirmar' | 'descartar';

type Opcoes = {
    id: string;
    aoAtualizar: (acao: AcaoProposta) => void;
    mensagemDeSucesso: string;
};

/** Motivos de um 422 de confirmação: `conflitos` vem por data, com a lista de conflitos de cada uma. */
function motivosDoConflito(corpo: Record<string, unknown> | null): string[] {
    const conflitos = corpo?.conflitos;

    if (!Array.isArray(conflitos)) {
        return [];
    }

    return conflitos.flatMap((porData) => {
        if (typeof porData !== 'object' || porData === null) {
            return [];
        }

        const { data, conflitos: itens } = porData as {
            data?: unknown;
            conflitos?: unknown;
        };

        if (typeof data !== 'string' || !Array.isArray(itens)) {
            return [];
        }

        return itens.flatMap((item) =>
            typeof item === 'object' &&
            item !== null &&
            typeof (item as { mensagem?: unknown }).mensagem === 'string'
                ? [
                      `${rotuloDoDia(data)}: ${(item as { mensagem: string }).mensagem}`,
                  ]
                : [],
        );
    });
}

/**
 * Confirmar ou descartar uma proposta do assistente, para qualquer tipo de cartão.
 * Duplo clique é bloqueado; um 422 traz o cartão atual, que substitui o antigo; confirmar recarrega a página.
 */
export function useConfirmacaoDeAcao({
    id,
    aoAtualizar,
    mensagemDeSucesso,
}: Opcoes) {
    const [processando, setProcessando] = useState<OperacaoDeAcao | null>(null);
    const [conflitos, setConflitos] = useState<string[]>([]);
    const emAndamento = useRef(false);

    async function executar(operacao: OperacaoDeAcao) {
        if (emAndamento.current) {
            return;
        }

        emAndamento.current = true;
        setProcessando(operacao);
        setConflitos([]);

        try {
            const url =
                operacao === 'confirmar'
                    ? confirmar(id).url
                    : descartar(id).url;
            const resposta = await enviarJson<{ acao: AcaoProposta }>(
                'POST',
                url,
            );

            if (resposta !== null) {
                aoAtualizar(resposta.acao);
            }

            if (operacao === 'confirmar') {
                toast.success(mensagemDeSucesso);
                // O resultado novo aparece no calendário ao lado: recarrega as props da página atual.
                router.reload();
            }
        } catch (erro) {
            if (erro instanceof ErroHttp && erro.status === 422) {
                setConflitos(motivosDoConflito(erro.corpo));

                // "Não está mais pendente": o 422 traz o cartão atual, que substitui o antigo (ex.: expirado).
                const atual = acaoDoCorpoDeErro(erro.corpo);

                if (atual !== null) {
                    aoAtualizar(atual);
                }
            }

            toast.error(mensagemDoErro(erro));
        } finally {
            emAndamento.current = false;
            setProcessando(null);
        }
    }

    return { processando, conflitos, executar };
}
