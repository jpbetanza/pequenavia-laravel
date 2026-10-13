import { router } from '@inertiajs/react';
import { CheckCheck } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { confirmar } from '@/actions/App/Http/Controllers/Assistente/AcaoPropostaController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    acaoDoCorpoDeErro,
    mensagemDoCorpoDeErro,
} from '@/lib/assistente/eventos';
import { ErroHttp, enviarJson, mensagemDoErro } from '@/lib/http';
import type { AcaoProposta } from '@/types/assistente';

type Props = {
    /** Propostas pendentes da resposta, na ordem dos cartões. */
    acoes: AcaoProposta[];
    aoAtualizarAcao: (acao: AcaoProposta) => void;
};

function tituloDa(acao: AcaoProposta): string {
    return acao.tipo === 'criar_reserva'
        ? acao.resumo.titulo
        : acao.cadastro.titulo;
}

/**
 * Confirma, uma por uma e na ordem, todas as propostas pendentes de uma resposta (ex.: uma importação de planilha).
 * Usa a mesma rota do botão de cada cartão; para na primeira recusa, que fica no cartão e num aviso.
 */
export function ConfirmarTodas({ acoes, aoAtualizarAcao }: Props) {
    const [progresso, setProgresso] = useState<number | null>(null);
    const emAndamento = useRef(false);

    async function confirmarTodas() {
        if (emAndamento.current) {
            return;
        }

        emAndamento.current = true;
        let confirmadas = 0;

        try {
            for (const acao of acoes) {
                setProgresso(confirmadas);

                try {
                    const resposta = await enviarJson<{ acao: AcaoProposta }>(
                        'POST',
                        confirmar(acao.id).url,
                    );

                    if (resposta !== null) {
                        aoAtualizarAcao(resposta.acao);
                    }

                    confirmadas++;
                } catch (erro) {
                    if (erro instanceof ErroHttp && erro.status === 422) {
                        const atual = acaoDoCorpoDeErro(erro.corpo);

                        if (atual !== null) {
                            aoAtualizarAcao(atual);
                        }

                        const motivo =
                            mensagemDoCorpoDeErro(erro.corpo) ??
                            mensagemDoErro(erro);

                        toast.error(
                            `Parei em “${tituloDa(acao)}”: ${motivo} As seguintes continuam pendentes.`,
                        );
                    } else {
                        toast.error(mensagemDoErro(erro));
                    }

                    return;
                }
            }
        } finally {
            if (confirmadas > 0) {
                toast.success(
                    confirmadas === 1
                        ? '1 proposta confirmada.'
                        : `${confirmadas} propostas confirmadas.`,
                );
                // Os cadastros e reservas novos aparecem nas telas ao lado.
                router.reload();
            }

            emAndamento.current = false;
            setProgresso(null);
        }
    }

    return (
        <Button
            type="button"
            onClick={() => void confirmarTodas()}
            disabled={progresso !== null}
            className="min-h-11 self-start"
        >
            {progresso === null ? (
                <CheckCheck aria-hidden="true" />
            ) : (
                <Spinner />
            )}
            {progresso === null
                ? `Confirmar todas (${acoes.length})`
                : `Confirmando ${progresso + 1} de ${acoes.length}…`}
        </Button>
    );
}
