import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { DialogoCorrigir } from '@/components/gestao/dialogo-corrigir';
import { Button } from '@/components/ui/button';
import {
    formatarCusto,
    formatarInstante,
    formatarNumero,
    rotuloDaFrustracao,
} from '@/lib/gestao/formatar';
import { cn } from '@/lib/utils';
import { show as rotaDoCliente } from '@/routes/gestao/clientes';
import { index as rotaDosTemas } from '@/routes/gestao/temas';
import type {
    ConfiancaDoCampo,
    ConversaSelecionada,
    OpcoesDeConversas,
    TemaDaConversa,
} from '@/types/gestao';

type Props = {
    conversa: ConversaSelecionada;
    opcoes: OpcoesDeConversas;
};

const DUAS_CASAS = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const ORIGEM_DO_TEMA: Record<TemaDaConversa['origem'], string> = {
    jev: 'JEV',
    haiku: 'Haiku',
    gestor: 'corrigido',
};

/** Linha de apoio de um campo: a confiança do JEV, ou "corrigido" quando o gestor trocou o valor. */
function apoioDoCampo(
    campo: Pick<ConfiancaDoCampo, 'confianca'> | null,
    corrigida: boolean,
): string | null {
    if (campo?.confianca != null) {
        return `confiança ${DUAS_CASAS.format(campo.confianca)}`;
    }

    return corrigida ? 'corrigido' : null;
}

function apoioDoTema(tema: TemaDaConversa): string {
    if (tema.origem === 'gestor') {
        return 'corrigido';
    }

    const origem = ORIGEM_DO_TEMA[tema.origem];

    return tema.confianca != null
        ? `${origem} · confiança ${DUAS_CASAS.format(tema.confianca)}`
        : origem;
}

/** O que o classificador viu numa conversa: campos com confiança, resumo, transcrição e correção. */
export function DetalheDaConversa({ conversa, opcoes }: Props) {
    const [corrigindo, setCorrigindo] = useState(false);
    const { caso, resultado, frustracao, tema, tokens } = conversa;
    const tokensTotal = tokens.entrada + tokens.saida;

    const chips: string[] = [];

    if (conversa.ferramentas.length > 0) {
        chips.push(`ferramentas: ${conversa.ferramentas.join(', ')}`);
    }

    chips.push(`${formatarNumero(tokensTotal)} tokens`);

    if (conversa.custo_assistente !== null) {
        chips.push(`assistente ${formatarCusto(conversa.custo_assistente)}`);
    }

    if (conversa.modelo_jev) {
        chips.push(`JEV ${conversa.modelo_jev}`);
    }

    const alertas: string[] = [];

    if (conversa.abandonada) {
        alertas.push('abandonada');
    }

    if (conversa.confusa) {
        alertas.push('confusa');
    }

    if (conversa.erro_do_assistente) {
        alertas.push('erro do assistente');
    }

    return (
        <section
            aria-label="Conversa escolhida"
            className="flex min-w-0 flex-col overflow-hidden rounded-[18px] border border-border bg-card"
        >
            <div className="flex flex-col gap-3 border-b px-5 py-4">
                <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <h2 className="min-w-0 flex-1 font-serif text-2xl leading-tight font-semibold break-words">
                        {conversa.titulo ?? (
                            <span className="text-muted-foreground italic">
                                Sem título
                            </span>
                        )}
                    </h2>
                    <Link
                        href={rotaDoCliente.url({
                            user: conversa.cliente.id,
                        })}
                        className="inline-flex min-h-11 items-center text-sm font-semibold text-[#6E1F24] underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        {conversa.cliente.nome}
                    </Link>
                </div>
                <p className="text-xs text-muted-foreground">
                    {formatarInstante(conversa.quando)}
                    {conversa.corrigida && ' · classificação corrigida'}
                </p>

                <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm md:grid-cols-4">
                    <div>
                        <dt className="text-muted-foreground">Caso de uso</dt>
                        <dd className="font-semibold">
                            {caso?.rotulo ?? 'Sem classificação'}
                        </dd>
                        {apoioDoCampo(caso, conversa.corrigida) && (
                            <dd className="text-xs text-muted-foreground tabular-nums">
                                {apoioDoCampo(caso, conversa.corrigida)}
                            </dd>
                        )}
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Resultado</dt>
                        <dd
                            className={cn(
                                'font-semibold',
                                resultado?.valor === 'nao_resolvida' &&
                                    'text-[#A33A2A]',
                            )}
                        >
                            {resultado?.rotulo ?? 'Sem classificação'}
                        </dd>
                        {apoioDoCampo(resultado, conversa.corrigida) && (
                            <dd className="text-xs text-muted-foreground tabular-nums">
                                {apoioDoCampo(resultado, conversa.corrigida)}
                            </dd>
                        )}
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Frustração</dt>
                        <dd className="font-semibold">
                            {frustracao
                                ? rotuloDaFrustracao(frustracao.nota)
                                : 'Sem classificação'}
                        </dd>
                        {frustracao && (
                            <dd className="text-xs text-muted-foreground tabular-nums">
                                confiança{' '}
                                {DUAS_CASAS.format(frustracao.confianca)}
                            </dd>
                        )}
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Pedido não atendido
                        </dt>
                        <dd className="font-semibold">
                            {tema ? (
                                <Link
                                    href={rotaDosTemas.url({
                                        query: { tema: tema.id },
                                    })}
                                    className="text-[#6E1F24] underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    {tema.nome}
                                </Link>
                            ) : (
                                'Nenhum'
                            )}
                        </dd>
                        {tema && (
                            <dd className="text-xs text-muted-foreground tabular-nums">
                                {apoioDoTema(tema)}
                            </dd>
                        )}
                    </div>
                </dl>

                {conversa.resumo && (
                    <div className="flex flex-col gap-1 rounded-[10px] bg-[#F3E8CF] px-3 py-2.5 text-sm text-[#3E2D0E]">
                        <span className="flex flex-wrap items-center gap-2 text-xs font-semibold tracking-[0.08em] text-[#8A6420] uppercase">
                            Resumo
                            <span className="rounded-full bg-[#FBF8F2] px-2 py-0.5 font-semibold tracking-normal text-[#3E2D0E] normal-case">
                                escrito pelo Haiku
                            </span>
                        </span>
                        <p>{conversa.resumo}</p>
                    </div>
                )}

                <ul className="flex flex-wrap gap-1.5 text-xs">
                    {chips.map((chip) => (
                        <li
                            key={chip}
                            className="rounded-full bg-[#EFE8DB] px-2 py-0.5 text-[#4A3D38]"
                        >
                            {chip}
                        </li>
                    ))}
                    {alertas.map((alerta) => (
                        <li
                            key={alerta}
                            className="rounded-full bg-[#F3E8CF] px-2 py-0.5 font-semibold text-[#5A4214]"
                        >
                            {alerta}
                        </li>
                    ))}
                </ul>
            </div>

            <ol
                aria-label="Mensagens"
                className="flex max-h-[60vh] flex-col gap-3 overflow-y-auto bg-[#FBF8F2] p-5"
            >
                {conversa.mensagens.map((mensagem) =>
                    mensagem.de === 'secretaria' ? (
                        <li
                            key={mensagem.id}
                            className="max-w-[85%] self-end rounded-[14px_14px_4px_14px] bg-[#6E1F24] px-3.5 py-2 whitespace-pre-line text-[#FBF8F2]"
                        >
                            <span className="sr-only">Secretaria: </span>
                            {mensagem.texto}
                        </li>
                    ) : (
                        <li
                            key={mensagem.id}
                            className="max-w-[92%] whitespace-pre-line"
                        >
                            <span className="sr-only">Assistente: </span>
                            {mensagem.texto}
                        </li>
                    ),
                )}
                {conversa.mensagens.length === 0 && (
                    <li className="text-sm text-muted-foreground">
                        Sem mensagens para mostrar.
                    </li>
                )}
            </ol>

            <div className="flex flex-wrap gap-2 border-t px-5 py-3">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setCorrigindo(true)}
                    className="min-h-11 sm:min-h-9"
                >
                    Corrigir classificação
                </Button>
            </div>

            <DialogoCorrigir
                conversa={conversa}
                opcoes={opcoes}
                aberto={corrigindo}
                aoFechar={() => setCorrigindo(false)}
            />
        </section>
    );
}
