import { Link, usePage } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useAssistente } from '@/hooks/use-assistente';
import { useClipboard } from '@/hooks/use-clipboard';
import { aplicarAoRoteiro } from '@/lib/primeiros-passos/acoes';
import { linkDoPasso } from '@/lib/primeiros-passos/links';
import {
    numeroDoPasso,
    pedidoDoPasso,
    porcentagem,
    selosDosCadastros,
} from '@/lib/primeiros-passos/roteiro';
import { cn } from '@/lib/utils';
import type { PassoDoRoteiro, PrimeirosPassos } from '@/types/primeiros-passos';

type Props = {
    roteiro: PrimeirosPassos;
};

function titulo(pendentes: number): string {
    return pendentes === 1 ? 'Falta um passo' : `Faltam ${pendentes} passos`;
}

function BlocoDoLink({ link }: { link: string }) {
    const [, copiar] = useClipboard();

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center gap-2 rounded-xl border bg-background py-1.5 pr-1.5 pl-3">
                <span className="min-w-0 flex-1 truncate tabular-nums">
                    {link}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-10"
                    onClick={async () => {
                        if (await copiar(link)) {
                            toast.success('Link copiado.');
                        }
                    }}
                >
                    <Copy aria-hidden="true" />
                    Copiar
                </Button>
            </div>
            <Button
                type="button"
                variant="link"
                className="h-auto min-h-11 self-start p-0 font-semibold"
                onClick={() => aplicarAoRoteiro('link_enviado')}
            >
                Já enviei aos grupos
            </Button>
        </div>
    );
}

function CartaoDoPasso({
    roteiro,
    passo,
}: {
    roteiro: PrimeirosPassos;
    passo: PassoDoRoteiro;
}) {
    const { assistente } = usePage().props;
    const { perguntar } = useAssistente();
    const atual = passo.chave === roteiro.atual;
    const comAssistente = assistente?.disponivel === true;

    return (
        <li
            className={cn(
                'flex flex-col gap-2.5 rounded-2xl p-4',
                atual ? 'bg-primary/10' : 'border',
            )}
        >
            <span
                className={cn(
                    'text-xs font-semibold',
                    atual ? 'text-primary' : 'text-muted-foreground',
                )}
            >
                Passo {numeroDoPasso(roteiro, passo.chave)}
                {atual ? ' · o próximo' : ''}
            </span>
            <b className="text-base">{passo.titulo}</b>
            <span className="text-muted-foreground">{passo.descricao}</span>

            {passo.chave === 'formulario' &&
                roteiro.link_do_formulario !== null && (
                    <BlocoDoLink link={roteiro.link_do_formulario} />
                )}

            <div className="flex flex-wrap items-center gap-2">
                {passo.chave === 'avisos' ? (
                    <Button asChild variant="outline" className="min-h-11">
                        <Link href={linkDoPasso('avisos')}>
                            Ativar neste aparelho
                        </Link>
                    </Button>
                ) : comAssistente ? (
                    <Button
                        type="button"
                        variant={atual ? 'default' : 'outline'}
                        className="min-h-11"
                        onClick={() => perguntar(pedidoDoPasso(passo.chave))}
                    >
                        Pedir ao assistente
                    </Button>
                ) : (
                    <Button asChild variant="outline" className="min-h-11">
                        <Link href={linkDoPasso(passo.chave)}>Abrir</Link>
                    </Button>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    className="min-h-11 text-muted-foreground"
                    onClick={() => aplicarAoRoteiro('pular', passo.chave)}
                >
                    Pular
                </Button>
            </div>
        </li>
    );
}

/**
 * Faixa clara do Início no meio do roteiro: o que já ficou pronto e um cartão por passo que falta, cada um com o
 * atalho para o assistente (ou para a tela) e "Pular".
 */
export function FaixaDeAndamento({ roteiro }: Props) {
    const pendentes = roteiro.passos.filter(
        (passo) => !passo.feito && !passo.pulado,
    );
    const selos = selosDosCadastros(roteiro.contagens);

    return (
        <section
            aria-labelledby="faixa-do-roteiro"
            className="flex min-w-0 flex-col gap-4 rounded-[18px] border bg-card p-5 sm:p-6"
        >
            <div className="flex flex-wrap items-center gap-4">
                <div className="flex min-w-0 flex-[1_1_260px] flex-col gap-0.5">
                    <span className="text-xs font-semibold tracking-[0.14em] text-primary uppercase">
                        Primeiros passos · {roteiro.feitos} de {roteiro.total}
                    </span>
                    <h2
                        id="faixa-do-roteiro"
                        className="font-serif text-3xl leading-tight font-semibold"
                    >
                        {titulo(pendentes.length)}
                    </h2>
                </div>
                {selos.length > 0 && (
                    <ul aria-label="Feito" className="flex flex-wrap gap-1.5">
                        {selos.map((selo) => (
                            <li
                                key={selo}
                                className="flex items-center gap-1.5 rounded-full bg-sucesso-fundo px-2.5 py-1 text-[13px] font-semibold text-sucesso"
                            >
                                <Check
                                    aria-hidden="true"
                                    className="size-3.5"
                                />
                                {selo}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <div
                role="progressbar"
                aria-label="Primeiros passos"
                aria-valuemin={0}
                aria-valuemax={roteiro.total}
                aria-valuenow={roteiro.feitos}
                className="h-1.5 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className="h-full rounded-full bg-primary transition-[width]"
                    style={{ width: `${porcentagem(roteiro)}%` }}
                />
            </div>
            <ul className="grid grid-cols-1 gap-3 @2xl:grid-cols-2 @5xl:grid-cols-3">
                {pendentes.map((passo) => (
                    <CartaoDoPasso
                        key={passo.chave}
                        roteiro={roteiro}
                        passo={passo}
                    />
                ))}
            </ul>
            <button
                type="button"
                onClick={() => aplicarAoRoteiro('adiar')}
                className="min-h-11 self-end px-2 text-muted-foreground underline underline-offset-4 hover:text-foreground"
            >
                Agora não, me lembre depois
            </button>
        </section>
    );
}
