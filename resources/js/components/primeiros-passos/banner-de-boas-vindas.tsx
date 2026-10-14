import { Link, usePage } from '@inertiajs/react';
import { Camera, Check, FileSpreadsheet, Sparkles, X } from 'lucide-react';
import { PerguntaAoAssistente } from '@/components/inicio/saudacao-do-inicio';
import { Button } from '@/components/ui/button';
import { useAssistente } from '@/hooks/use-assistente';
import { aplicarAoRoteiro } from '@/lib/primeiros-passos/acoes';
import { linkDoPasso } from '@/lib/primeiros-passos/links';
import {
    PEDIDO_DO_ARQUIVO,
    PEDIDO_DO_ROTEIRO,
    pedidoDoPasso,
    selosDosCadastros,
} from '@/lib/primeiros-passos/roteiro';
import { cn } from '@/lib/utils';
import type { PrimeirosPassos } from '@/types/primeiros-passos';
import { SoltarArquivo } from './soltar-arquivo';

type Props = {
    roteiro: PrimeirosPassos;
    nome: string;
};

/** Primeiro nome, para a saudação. */
function primeiroNome(nome: string): string {
    return nome.trim().split(/\s+/)[0] ?? nome;
}

/**
 * Banner escuro do topo do Início no primeiro acesso: boas-vindas, o roteiro e o caminho pelo assistente (descrever
 * do seu jeito ou soltar uma planilha). Sem o assistente ligado, cada passo leva à tela correspondente.
 */
export function BannerDeBoasVindas({ roteiro, nome }: Props) {
    const { assistente } = usePage().props;
    const { perguntar } = useAssistente();
    const comAssistente = assistente?.disponivel === true;
    const enviarArquivos = (arquivos: File[]) =>
        perguntar(PEDIDO_DO_ARQUIVO, arquivos);

    return (
        <section
            aria-labelledby="boas-vindas"
            className="grid min-w-0 grid-cols-1 gap-8 rounded-[22px] bg-foreground p-6 text-background sm:p-8 @4xl:grid-cols-2"
        >
            <div className="flex flex-col gap-4">
                <p className="text-[13px] font-semibold tracking-[0.14em] text-dourado-claro uppercase">
                    Primeiros passos · {roteiro.feitos} de {roteiro.total}
                </p>
                <h1
                    id="boas-vindas"
                    className="font-serif text-4xl leading-none font-semibold sm:text-5xl"
                >
                    Boas-vindas, {primeiroNome(nome)}.
                </h1>
                <p className="max-w-[46ch] text-base text-background/80 sm:text-[17px]">
                    {comAssistente
                        ? 'Em uns dez minutos a agenda da paróquia fica pronta. Conte ao assistente como são as salas, do seu jeito, ou solte uma planilha que você já tem. Ele prepara tudo e você só confirma.'
                        : 'Em uns dez minutos a agenda da paróquia fica pronta. Siga os passos ao lado, na ordem.'}
                </p>

                <div className="flex flex-wrap gap-2.5">
                    {comAssistente && (
                        <Button
                            type="button"
                            onClick={() => perguntar(PEDIDO_DO_ROTEIRO)}
                            className="min-h-12 rounded-xl bg-dourado-claro px-5 text-[15px] text-foreground hover:bg-dourado-claro/90"
                        >
                            <Sparkles aria-hidden="true" />
                            Configurar com o assistente
                        </Button>
                    )}
                    <button
                        type="button"
                        onClick={() => aplicarAoRoteiro('dispensar')}
                        className="min-h-12 rounded-xl border border-background/30 px-4 text-[15px] font-semibold hover:bg-background/10"
                    >
                        Prefiro configurar por conta própria
                    </button>
                </div>

                {comAssistente && (
                    <>
                        <SoltarArquivo
                            id="arquivo-boas-vindas"
                            aoEscolher={enviarArquivos}
                            className="hidden items-center gap-3.5 rounded-2xl border-background/35 p-4 data-[arrastando]:bg-background/10 md:flex"
                        >
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-background/10 text-dourado-claro">
                                <FileSpreadsheet aria-hidden="true" />
                            </span>
                            <span className="flex flex-col gap-0.5">
                                <span className="text-[15px] font-semibold">
                                    Já tem uma planilha das salas, equipamentos
                                    ou horários?
                                </span>
                                <span className="text-background/70">
                                    Solte aqui ou clique para escolher. O
                                    assistente lê e propõe o cadastro.
                                </span>
                            </span>
                        </SoltarArquivo>
                        <SoltarArquivo
                            id="foto-boas-vindas"
                            camera
                            aoEscolher={enviarArquivos}
                            className="flex min-h-11 items-center justify-center gap-2 rounded-xl border-background/35 font-semibold md:hidden"
                        >
                            <Camera aria-hidden="true" className="size-5" />
                            Fotografar uma lista
                        </SoltarArquivo>
                    </>
                )}
            </div>

            <div className="flex flex-col gap-3">
                <div className="flex items-baseline justify-between gap-3">
                    <h2 className="text-[15px] font-semibold">O roteiro</h2>
                    {comAssistente && (
                        <span className="text-[13px] text-background/70">
                            cada passo dá para pedir ao assistente
                        </span>
                    )}
                </div>
                <ol className="flex flex-col gap-2">
                    {roteiro.passos.map((passo, indice) => {
                        const resolvido = passo.feito || passo.pulado;
                        const atual = passo.chave === roteiro.atual;

                        return (
                            <li
                                key={passo.chave}
                                className="grid grid-cols-[2rem_minmax(0,1fr)] items-center gap-3 rounded-2xl bg-background/[0.06] py-3 pr-3 pl-3.5 sm:grid-cols-[2rem_minmax(0,1fr)_auto]"
                            >
                                <span
                                    className={cn(
                                        'flex size-7 items-center justify-center rounded-full border-[1.5px] text-[13px] font-semibold',
                                        atual
                                            ? 'border-dourado-claro text-dourado-claro'
                                            : 'border-background/30 text-background/70',
                                        resolvido &&
                                            'border-dourado-claro bg-dourado-claro text-foreground',
                                    )}
                                >
                                    {resolvido ? (
                                        <Check
                                            aria-label="Feito"
                                            className="size-4"
                                        />
                                    ) : (
                                        indice + 1
                                    )}
                                </span>
                                <span className="min-w-0">
                                    <b>{passo.titulo}</b>
                                    <br />
                                    <span className="text-background/70">
                                        {passo.descricao}
                                    </span>
                                </span>
                                {!resolvido && (
                                    <span className="col-span-2 flex sm:col-span-1">
                                        {comAssistente &&
                                        passo.chave !== 'avisos' ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    perguntar(
                                                        pedidoDoPasso(
                                                            passo.chave,
                                                        ),
                                                    )
                                                }
                                                className={cn(
                                                    'min-h-11 w-full rounded-xl px-3.5 font-semibold whitespace-nowrap sm:w-auto',
                                                    atual
                                                        ? 'bg-background text-foreground hover:bg-background/90'
                                                        : 'border border-background/30 hover:bg-background/10',
                                                )}
                                            >
                                                {atual
                                                    ? 'Começar'
                                                    : 'Pedir ao assistente'}
                                            </button>
                                        ) : (
                                            <Link
                                                href={linkDoPasso(passo.chave)}
                                                className="flex min-h-11 w-full items-center justify-center rounded-xl border border-background/30 px-3.5 font-semibold whitespace-nowrap hover:bg-background/10 sm:w-auto"
                                            >
                                                {passo.chave === 'avisos'
                                                    ? 'Como ativar'
                                                    : 'Abrir'}
                                            </Link>
                                        )}
                                    </span>
                                )}
                            </li>
                        );
                    })}
                </ol>
                <button
                    type="button"
                    onClick={() => aplicarAoRoteiro('adiar')}
                    className="min-h-11 self-end px-2 text-background/70 underline underline-offset-4 hover:text-background"
                >
                    Agora não, me lembre depois
                </button>
            </div>
        </section>
    );
}

/** Banner escuro do fim do roteiro: o que ficou pronto e a pergunta ao assistente. Fecha de vez no X. */
export function BannerDeConcluido({ roteiro, nome }: Props) {
    const { assistente } = usePage().props;
    const selos = selosDosCadastros(roteiro.contagens);
    const formulario = roteiro.passos.find(
        (passo) => passo.chave === 'formulario',
    );
    const avisos = roteiro.passos.find((passo) => passo.chave === 'avisos');

    if (formulario?.feito) {
        selos.push('Link enviado');
    }

    if (avisos?.feito) {
        selos.push('Avisos no celular');
    }

    return (
        <section
            aria-labelledby="tudo-pronto"
            className="relative grid min-w-0 grid-cols-1 items-start gap-8 rounded-[22px] bg-foreground p-6 text-background sm:p-8 @4xl:grid-cols-2"
        >
            <button
                type="button"
                aria-label="Fechar e não mostrar de novo"
                onClick={() => aplicarAoRoteiro('dispensar')}
                className="absolute top-3 right-3 flex size-11 items-center justify-center rounded-xl text-background/70 hover:bg-background/10 hover:text-background"
            >
                <X aria-hidden="true" />
            </button>
            <div className="flex flex-col gap-3.5 pr-10">
                <p className="text-[13px] font-semibold tracking-[0.14em] text-dourado-claro uppercase">
                    Primeiros passos · {roteiro.feitos} de {roteiro.total}
                </p>
                <h1
                    id="tudo-pronto"
                    className="font-serif text-4xl leading-none font-semibold sm:text-5xl"
                >
                    Tudo pronto, {primeiroNome(nome)}.
                </h1>
                <p className="max-w-[44ch] text-base text-background/80 sm:text-[17px]">
                    A agenda está montada. Quando chegar um pedido pelo
                    formulário, ele aparece aqui
                    {avisos?.feito ? ' e no seu celular' : ''}.
                </p>
                {selos.length > 0 && (
                    <ul
                        aria-label="O que foi configurado"
                        className="flex flex-wrap gap-2"
                    >
                        {selos.map((selo) => (
                            <li
                                key={selo}
                                className="rounded-full bg-background/10 px-3 py-1.5 font-semibold"
                            >
                                {selo}
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {assistente?.disponivel && (
                <PerguntaAoAssistente
                    rotulo="Daqui para frente, é só pedir"
                    sugestoes={[
                        'Reserve o salão para o coral toda quinta às 19h',
                        'Quais pedidos estão esperando resposta?',
                        'Como gero certificados?',
                    ]}
                />
            )}
        </section>
    );
}
