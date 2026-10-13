import { Link } from '@inertiajs/react';
import { Button, buttonVariants } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { linkDoCadastro, rotuloDoSelo } from '@/lib/assistente/cadastro';
import { cn } from '@/lib/utils';
import type { AcaoProposta, AcaoPropostaDeCadastro } from '@/types/assistente';
import { useConfirmacaoDeAcao } from './use-confirmacao-de-acao';

type Props = {
    acao: AcaoPropostaDeCadastro;
    aoAtualizar: (acao: AcaoProposta) => void;
};

export const CLASSES_DO_SELO: Record<AcaoPropostaDeCadastro['status'], string> =
    {
        proposta: 'bg-aviso-fundo text-aviso',
        confirmada: 'bg-sucesso-fundo text-sucesso',
        descartada: 'bg-muted text-muted-foreground',
        expirada: 'bg-muted text-muted-foreground',
    };

/** Valor de uma linha: "antes → depois", com o antes riscado. Null vira "—". */
export function ValorDaLinha({
    antes,
    depois,
}: {
    antes: string | null;
    depois: string | null;
}) {
    return (
        <>
            {antes !== null && (
                <>
                    <span className="text-muted-foreground line-through">
                        {antes}
                    </span>
                    <span aria-hidden="true" className="mx-1.5">
                        →
                    </span>
                    <span className="sr-only">para </span>
                </>
            )}
            <span className="font-medium">{depois ?? '—'}</span>
        </>
    );
}

export function CartaoDeCadastroProposto({ acao, aoAtualizar }: Props) {
    const { cadastro, resultado } = acao;
    const excluir = cadastro.operacao === 'excluir';
    const proposta = acao.status === 'proposta';
    const { processando, recusa, executar } = useConfirmacaoDeAcao({
        id: acao.id,
        aoAtualizar,
        mensagemDeSucesso: excluir ? 'Cadastro excluído.' : 'Cadastro salvo.',
    });

    return (
        <section
            aria-label="Cadastro proposto"
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
                {rotuloDoSelo(acao.status, cadastro.operacao)}
            </span>

            <p className="text-base font-semibold">{cadastro.titulo}</p>

            {cadastro.linhas.length > 0 && (
                <dl className="grid grid-cols-[6rem_minmax(0,1fr)] gap-x-3 gap-y-1.5">
                    {cadastro.linhas.map((linha, i) => (
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

            {cadastro.avisos.length > 0 && (
                <ul className="flex list-disc flex-col gap-1 rounded-lg bg-aviso-fundo p-2.5 pl-7 text-aviso">
                    {cadastro.avisos.map((aviso, i) => (
                        <li key={`${aviso}-${i}`}>{aviso}</li>
                    ))}
                </ul>
            )}

            {recusa !== null && (
                <p role="alert" className="font-medium text-destructive">
                    {recusa}
                </p>
            )}

            {proposta && (
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant={excluir ? 'destructive' : 'default'}
                        className="min-h-11 flex-1 basis-36"
                        disabled={processando !== null}
                        onClick={() => executar('confirmar')}
                    >
                        {processando === 'confirmar' && <Spinner />}
                        {excluir ? 'Confirmar exclusão' : 'Confirmar'}
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

            {acao.status === 'confirmada' && (
                <Link
                    href={linkDoCadastro(
                        cadastro.entidade,
                        resultado?.cadastro_id ?? null,
                    )}
                    className={cn(
                        buttonVariants({ variant: 'outline' }),
                        'min-h-11 self-start',
                    )}
                >
                    Ver cadastro
                </Link>
            )}
        </section>
    );
}
