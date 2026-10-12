import { Link, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { useAssistente } from '@/hooks/use-assistente';
import { useIsMobile } from '@/hooks/use-mobile';
import {
    confirmar,
    descartar,
} from '@/actions/App/Http/Controllers/Assistente/AcaoPropostaController';
import { Button, buttonVariants } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { acaoDoCorpoDeErro } from '@/lib/assistente/eventos';
import { ErroHttp, enviarJson, mensagemDoErro } from '@/lib/http';
import { cn } from '@/lib/utils';
import { calendario } from '@/routes/agendamentos';
import type { AcaoProposta } from '@/types/assistente';
import { faixaDeHoras, rotuloDoDia } from './formatos';

type Props = {
    acao: AcaoProposta;
    aoAtualizar: (acao: AcaoProposta) => void;
};

const SELOS: Record<
    AcaoProposta['status'],
    { rotulo: string; classes: string }
> = {
    proposta: {
        rotulo: 'Aguardando sua confirmação',
        classes: 'bg-aviso-fundo text-aviso',
    },
    confirmada: {
        rotulo: 'Reserva criada',
        classes: 'bg-sucesso-fundo text-sucesso',
    },
    descartada: {
        rotulo: 'Descartada',
        classes: 'bg-muted text-muted-foreground',
    },
    expirada: { rotulo: 'Expirada', classes: 'bg-muted text-muted-foreground' },
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

export function CartaoDeAcaoProposta({ acao, aoAtualizar }: Props) {
    const [processando, setProcessando] = useState<
        'confirmar' | 'descartar' | null
    >(null);
    const [conflitos, setConflitos] = useState<string[]>([]);
    const { fechar } = useAssistente();
    const celular = useIsMobile();
    const emAndamento = useRef(false);
    const { resumo } = acao;
    const selo = SELOS[acao.status];
    const proposta = acao.status === 'proposta';

    async function executar(operacao: 'confirmar' | 'descartar') {
        if (emAndamento.current) {
            return;
        }

        emAndamento.current = true;
        setProcessando(operacao);
        setConflitos([]);

        try {
            const url =
                operacao === 'confirmar'
                    ? confirmar(acao.id).url
                    : descartar(acao.id).url;
            const resposta = await enviarJson<{ acao: AcaoProposta }>(
                'POST',
                url,
            );

            if (resposta !== null) {
                aoAtualizar(resposta.acao);
            }

            if (operacao === 'confirmar') {
                toast.success('Reserva criada.');
                // A reserva nova aparece no calendário ao lado: recarrega as props da página atual.
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

    // Formato `id:quantidade,id:quantidade`, lido por lerPedidoDeNovaReserva.
    const equipamentosNaUrl = resumo.equipamentos
        .map((item) => `${item.id}:${item.quantidade}`)
        .join(',');
    const quando = `${rotuloDoDia(resumo.data)}, ${faixaDeHoras(resumo.inicio, resumo.fim)}`;

    return (
        <section
            aria-label="Reserva proposta"
            className={cn(
                'flex flex-col gap-3 rounded-xl border bg-background p-3.5 text-sm',
                !proposta && 'opacity-60',
            )}
        >
            <span
                className={cn(
                    'inline-flex w-fit items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                    selo.classes,
                )}
            >
                {selo.rotulo}
            </span>

            <dl className="grid grid-cols-[6rem_minmax(0,1fr)] gap-x-3 gap-y-1.5">
                <dt className="text-muted-foreground">Título</dt>
                <dd className="font-semibold">{resumo.titulo}</dd>

                {resumo.grupo_nome !== null && (
                    <>
                        <dt className="text-muted-foreground">Grupo</dt>
                        <dd>{resumo.grupo_nome}</dd>
                    </>
                )}

                <dt className="text-muted-foreground">Espaço</dt>
                <dd>{resumo.espaco_nome}</dd>

                <dt className="text-muted-foreground">Quando</dt>
                <dd className="tabular-nums">{quando}</dd>

                {resumo.equipamentos.length > 0 && (
                    <>
                        <dt className="text-muted-foreground">Equipamentos</dt>
                        <dd>
                            {resumo.equipamentos
                                .map(
                                    (item) =>
                                        `${item.nome} (${item.quantidade})`,
                                )
                                .join(', ')}
                        </dd>
                    </>
                )}

                {resumo.pessoas !== null && (
                    <>
                        <dt className="text-muted-foreground">Pessoas</dt>
                        <dd>{resumo.pessoas}</dd>
                    </>
                )}

                {resumo.observacoes !== null && resumo.observacoes !== '' && (
                    <>
                        <dt className="text-muted-foreground">Observações</dt>
                        <dd className="whitespace-pre-wrap">
                            {resumo.observacoes}
                        </dd>
                    </>
                )}
            </dl>

            {proposta && (
                <p className="flex items-center gap-1.5 text-sm font-semibold text-sucesso">
                    <Check aria-hidden="true" className="size-4" />
                    Sem conflito
                </p>
            )}

            {conflitos.length > 0 && (
                <ul className="list-disc pl-5 text-destructive">
                    {conflitos.map((motivo) => (
                        <li key={motivo}>{motivo}</li>
                    ))}
                </ul>
            )}

            {proposta && (
                <div className="flex flex-wrap gap-2">
                    <Button
                        className="min-h-11 flex-1 basis-36"
                        disabled={processando !== null}
                        onClick={() => executar('confirmar')}
                    >
                        {processando === 'confirmar' && <Spinner />}
                        Confirmar reserva
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
                    {resumo.espaco_id !== null && (
                        <Link
                            href={calendario.url({
                                query: {
                                    data: resumo.data,
                                    visao: 'dia',
                                    nova_reserva: '1',
                                    espaco: resumo.espaco_id,
                                    inicio: resumo.inicio,
                                    titulo: resumo.titulo,
                                    fim: resumo.fim,
                                    grupo: resumo.grupo_id ?? undefined,
                                    equipamentos:
                                        equipamentosNaUrl || undefined,
                                    pessoas: resumo.pessoas ?? undefined,
                                    observacoes:
                                        resumo.observacoes ?? undefined,
                                },
                            })}
                            onClick={() => {
                                // No celular o painel é tela cheia: fecha para a reserva aparecer. Na mesma página o caminho não muda, então o painel não fecha sozinho.
                                if (celular) {
                                    fechar();
                                }
                            }}
                            className={cn(
                                buttonVariants({ variant: 'outline' }),
                                'min-h-11 flex-1 basis-36',
                            )}
                        >
                            Abrir no painel
                        </Link>
                    )}
                </div>
            )}

            {acao.status === 'confirmada' && acao.resultado !== null && (
                <Link
                    href={calendario.url({
                        query: { data: acao.resultado.data },
                    })}
                    className="inline-flex min-h-11 items-center font-semibold text-primary underline-offset-4 hover:underline"
                >
                    Ver no calendário
                </Link>
            )}
        </section>
    );
}
