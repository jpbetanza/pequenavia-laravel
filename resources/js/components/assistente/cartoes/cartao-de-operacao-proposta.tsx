import { Link } from '@inertiajs/react';
import { Button, buttonVariants } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { AcaoProposta, AcaoPropostaDeOperacao } from '@/types/assistente';
import { CLASSES_DO_SELO, ValorDaLinha } from './cartao-de-cadastro-proposto';
import { useConfirmacaoDeAcao } from './use-confirmacao-de-acao';

type Props = {
    acao: AcaoPropostaDeOperacao;
    aoAtualizar: (acao: AcaoProposta) => void;
};

/** Texto do selo: o "feito" vem do servidor (ex.: "Aprovada", "Entregue"). */
function rotuloDoSelo(acao: AcaoPropostaDeOperacao): string {
    switch (acao.status) {
        case 'proposta':
            return 'Aguardando sua confirmação';

        case 'confirmada':
            return acao.operacao.feito;

        case 'descartada':
            return 'Descartada';

        case 'expirada':
            return 'Expirada';
    }
}

/**
 * Responder solicitação, alterar ou cancelar reserva, marcar equipamento: o mesmo layout do cadastro proposto,
 * com os textos do botão e do selo vindos do servidor.
 */
export function CartaoDeOperacaoProposta({ acao, aoAtualizar }: Props) {
    const { operacao, resultado } = acao;
    const proposta = acao.status === 'proposta';
    const { processando, conflitos, recusa, executar } = useConfirmacaoDeAcao({
        id: acao.id,
        aoAtualizar,
        mensagemDeSucesso: operacao.sucesso,
    });

    return (
        <section
            aria-label="Ação proposta"
            className={cn(
                'flex flex-col gap-3 rounded-xl border bg-background p-3.5 text-sm',
                !proposta && 'opacity-60',
            )}
        >
            <span
                className={cn(
                    'inline-flex w-fit items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                    CLASSES_DO_SELO[acao.status],
                )}
            >
                {rotuloDoSelo(acao)}
            </span>

            <p className="text-base font-semibold">{operacao.titulo}</p>

            {operacao.linhas.length > 0 && (
                <dl className="grid grid-cols-[6rem_minmax(0,1fr)] gap-x-3 gap-y-1.5">
                    {operacao.linhas.map((linha, i) => (
                        <div key={`${linha.rotulo}-${i}`} className="contents">
                            <dt className="text-muted-foreground">
                                {linha.rotulo}
                            </dt>
                            <dd className="tabular-nums">
                                <ValorDaLinha
                                    antes={linha.antes}
                                    depois={linha.depois}
                                />
                            </dd>
                        </div>
                    ))}
                </dl>
            )}

            {operacao.avisos.length > 0 && (
                <ul className="flex list-disc flex-col gap-1 rounded-lg bg-aviso-fundo p-2.5 pl-7 text-aviso">
                    {operacao.avisos.map((aviso, i) => (
                        <li key={`${aviso}-${i}`}>{aviso}</li>
                    ))}
                </ul>
            )}

            {(recusa !== null || conflitos.length > 0) && (
                <div
                    role="alert"
                    className="flex flex-col gap-1 text-destructive"
                >
                    {recusa !== null && <p className="font-medium">{recusa}</p>}
                    {conflitos.map((conflito) => (
                        <p key={conflito}>{conflito}</p>
                    ))}
                </div>
            )}

            {proposta && (
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant={
                            operacao.destrutiva ? 'destructive' : 'default'
                        }
                        className="min-h-11 flex-1 basis-36"
                        disabled={processando !== null}
                        onClick={() => executar('confirmar')}
                    >
                        {processando === 'confirmar' && <Spinner />}
                        {operacao.botao}
                    </Button>
                    <Button
                        variant="outline"
                        className="min-h-11 flex-1 basis-32"
                        disabled={processando !== null}
                        onClick={() => executar('descartar')}
                    >
                        {processando === 'descartar' && <Spinner />}
                        Descartar
                    </Button>
                </div>
            )}

            {acao.status === 'confirmada' && resultado !== null && (
                <Link
                    href={resultado.link}
                    className={cn(
                        buttonVariants({ variant: 'outline' }),
                        'min-h-11 self-start',
                    )}
                >
                    Ver na agenda
                </Link>
            )}
        </section>
    );
}
