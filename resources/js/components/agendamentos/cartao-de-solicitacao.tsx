import { Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    CircleAlert,
    Minus,
    MessageCircle,
    Plus,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import DialogoDeRecusa from '@/components/agendamentos/dialogo-de-recusa';
import { tratamentoDeErro } from '@/components/modelos/erro-de-acao';
import { Button, buttonVariants } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    aplicarSugestaoDeEspaco,
    aplicarSugestaoDeHorario,
    ajustarQuantidade,
    alternarEquipamento,
    edicaoDoCartao,
    estaEmManutencao,
    payloadDaEdicao,
    situacaoDaAprovacao,
    type EdicaoDaSolicitacao,
} from '@/lib/agenda/edicao-de-solicitacao';
import { formatarDiaCurto } from '@/lib/agenda/datas';
import { mascararTelefone } from '@/lib/agenda/formulario-publico';
import { linkDoWhatsApp } from '@/lib/agenda/whatsapp';
import { rotuloDeRecebida } from '@/lib/agenda/rotulo-de-recebida';
import { ErroHttp, enviarJson, mensagemDoErro } from '@/lib/http';
import { cn } from '@/lib/utils';
import { calendario as rotaDoCalendario } from '@/routes/agendamentos';
import {
    aprovar as rotaAprovar,
    recusar as rotaRecusar,
    update as rotaAtualizar,
} from '@/routes/agendamentos/solicitacoes';
import type {
    CartaoDeSolicitacao as Cartao,
    PropsDaSolicitacoes,
} from '@/types/agendamentos';

/** Espera depois da última mudança antes de gravar. */
const ATRASO_DO_SALVAMENTO = 600;

type Salvamento = 'salvo' | 'pendente' | 'salvando' | 'erro';

type Props = {
    cartao: Cartao;
    espacos: PropsDaSolicitacoes['espacos'];
    equipamentos: PropsDaSolicitacoes['equipamentos'];
};

/**
 * Cartão de uma solicitação. Pendentes são editáveis (salvas com PUT, com espera de 600 ms);
 * aprovadas e recusadas mostram o resumo. A página monta o cartão com `key` na foto do servidor,
 * então um recarregamento com dados novos reinicia o cartão do zero.
 */
export default function CartaoDeSolicitacao({
    cartao: inicial,
    espacos,
    equipamentos,
}: Props) {
    const [cartao, setCartao] = useState<Cartao>(inicial);
    const [rascunho, setRascunho] = useState<EdicaoDaSolicitacao>(() =>
        edicaoDoCartao(inicial),
    );
    const [salvamento, setSalvamento] = useState<Salvamento>('salvo');
    const [agora] = useState(() => new Date());
    const [acao, setAcao] = useState<'aprovar' | 'recusar' | null>(null);
    const [dialogoDeRecusa, setDialogoDeRecusa] = useState(false);

    const rascunhoRef = useRef(rascunho);
    const salvoRef = useRef(JSON.stringify(edicaoDoCartao(inicial)));
    const temporizadorRef = useRef<number | null>(null);
    const controleRef = useRef<AbortController | null>(null);

    useEffect(() => {
        return () => {
            if (temporizadorRef.current !== null) {
                window.clearTimeout(temporizadorRef.current);
            }

            controleRef.current?.abort();
        };
    }, []);

    const pendente = cartao.status === 'pendente';
    const contato = linkDoWhatsApp(cartao.telefone);
    const recebida = rotuloDeRecebida(cartao.recebida_em, agora);
    const aprovacao = situacaoDaAprovacao(cartao.estado);
    const ocupado = acao !== null || salvamento === 'salvando';

    function cancelarTemporizador() {
        if (temporizadorRef.current !== null) {
            window.clearTimeout(temporizadorRef.current);
            temporizadorRef.current = null;
        }
    }

    /** Grava o rascunho atual. Devolve o cartão recalculado pelo servidor, ou null se falhou ou foi substituído. */
    async function salvar(): Promise<Cartao | null> {
        cancelarTemporizador();
        controleRef.current?.abort();

        const controle = new AbortController();
        const enviado = rascunhoRef.current;

        controleRef.current = controle;
        setSalvamento('salvando');

        try {
            const resposta = await enviarJson<Cartao>(
                'PUT',
                rotaAtualizar.url({ solicitacao: cartao.id }),
                payloadDaEdicao(enviado),
                controle.signal,
            );

            salvoRef.current = JSON.stringify(enviado);

            if (resposta !== null) {
                setCartao(resposta);
            }

            setSalvamento(
                temporizadorRef.current === null ? 'salvo' : 'pendente',
            );

            return resposta;
        } catch (erro) {
            if (controle.signal.aborted) {
                return null;
            }

            setSalvamento('erro');

            if (
                erro instanceof ErroHttp &&
                Object.keys(erro.erros).length > 0
            ) {
                tratamentoDeErro.onError(erro.erros);
            } else {
                toast.error(mensagemDoErro(erro));
            }

            return null;
        } finally {
            if (controleRef.current === controle) {
                controleRef.current = null;
            }
        }
    }

    function alterar(proximo: EdicaoDaSolicitacao, imediato = false) {
        rascunhoRef.current = proximo;
        setRascunho(proximo);
        cancelarTemporizador();
        setSalvamento('pendente');

        if (imediato) {
            void salvar();

            return;
        }

        temporizadorRef.current = window.setTimeout(() => {
            temporizadorRef.current = null;
            void salvar();
        }, ATRASO_DO_SALVAMENTO);
    }

    async function aprovar() {
        if (ocupado) {
            return;
        }

        let atual: Cartao | null = cartao;

        if (JSON.stringify(rascunhoRef.current) !== salvoRef.current) {
            atual = await salvar();

            if (atual === null) {
                return;
            }
        }

        if (!situacaoDaAprovacao(atual.estado).pode) {
            return;
        }

        setAcao('aprovar');

        try {
            await enviarJson(
                'POST',
                rotaAprovar.url({ solicitacao: cartao.id }),
            );
            toast.success('Solicitação aprovada e reserva criada.');
        } catch (erro) {
            toast.error(mensagemDoErro(erro));
        } finally {
            setAcao(null);
            router.reload();
        }
    }

    async function recusar(motivo: string) {
        setAcao('recusar');

        try {
            await enviarJson(
                'POST',
                rotaRecusar.url({ solicitacao: cartao.id }),
                {
                    motivo_recusa: motivo === '' ? null : motivo,
                },
            );
            toast.success('Solicitação recusada.');
        } catch (erro) {
            toast.error(mensagemDoErro(erro));
        } finally {
            setAcao(null);
            setDialogoDeRecusa(false);
            router.reload();
        }
    }

    const idDoCartao = cartao.id;
    const horarioSugerido = cartao.sugestoes.horario;
    const espacoDoCartao = espacos.find(
        (espaco) => espaco.id === cartao.espaco_id,
    );

    return (
        <article
            aria-label={`Solicitação de ${cartao.nome}`}
            className="overflow-hidden rounded-2xl border bg-card"
        >
            <header className="flex flex-wrap items-start justify-between gap-4 p-5 pb-3">
                <div className="flex min-w-0 flex-col gap-1">
                    <h2 className="text-lg font-semibold">{cartao.titulo}</h2>
                    <p className="text-sm text-muted-foreground">
                        {[
                            cartao.nome,
                            cartao.grupo_nome,
                            mascararTelefone(cartao.telefone),
                        ]
                            .filter(
                                (parte): parte is string =>
                                    parte !== null && parte !== '',
                            )
                            .join(' · ')}
                    </p>
                    {recebida !== null && (
                        <p className="text-xs text-muted-foreground">
                            {recebida}
                        </p>
                    )}
                </div>
                <SeloDoCartao cartao={cartao} />
            </header>

            {pendente ? (
                <>
                    <div className="grid gap-3 px-5 pb-4 sm:grid-cols-2 lg:grid-cols-[1.6fr_1.2fr_0.8fr_0.8fr_0.7fr]">
                        <div className="flex min-w-0 flex-col gap-1.5">
                            <Label
                                htmlFor={`${idDoCartao}-espaco`}
                                className="text-sm"
                            >
                                Espaço
                            </Label>
                            <Select
                                value={rascunho.espaco_id ?? ''}
                                onValueChange={(valor) =>
                                    alterar({
                                        ...rascunhoRef.current,
                                        espaco_id: valor,
                                    })
                                }
                            >
                                <SelectTrigger
                                    id={`${idDoCartao}-espaco`}
                                    aria-invalid={
                                        cartao.estado === 'escolha_um_espaco'
                                            ? true
                                            : undefined
                                    }
                                    className={cn(
                                        'h-11 w-full bg-background',
                                        cartao.estado === 'escolha_um_espaco' &&
                                            'border-dashed',
                                    )}
                                >
                                    <SelectValue placeholder="Escolher espaço…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {espacos.map((espaco) => (
                                        <SelectItem
                                            key={espaco.id}
                                            value={espaco.id}
                                        >
                                            {espaco.nome}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <CampoDeHorario
                            id={`${idDoCartao}-data`}
                            rotulo="Data"
                            tipo="date"
                            valor={rascunho.data}
                            aoMudar={(valor) =>
                                alterar({ ...rascunhoRef.current, data: valor })
                            }
                        />
                        <CampoDeHorario
                            id={`${idDoCartao}-inicio`}
                            rotulo="Início"
                            tipo="time"
                            valor={rascunho.inicio}
                            aoMudar={(valor) =>
                                alterar({
                                    ...rascunhoRef.current,
                                    inicio: valor,
                                })
                            }
                        />
                        <CampoDeHorario
                            id={`${idDoCartao}-fim`}
                            rotulo="Fim"
                            tipo="time"
                            valor={rascunho.fim}
                            aoMudar={(valor) =>
                                alterar({ ...rascunhoRef.current, fim: valor })
                            }
                        />
                        <CampoDeHorario
                            id={`${idDoCartao}-pessoas`}
                            rotulo="Pessoas"
                            tipo="number"
                            valor={
                                rascunho.pessoas === null
                                    ? ''
                                    : String(rascunho.pessoas)
                            }
                            aoMudar={(valor) =>
                                alterar({
                                    ...rascunhoRef.current,
                                    pessoas:
                                        valor === '' ? null : Number(valor),
                                })
                            }
                        />
                    </div>

                    <fieldset className="flex flex-col gap-2 px-5 pb-4">
                        <legend className="text-sm font-medium">
                            Equipamentos
                        </legend>
                        {equipamentos.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Nenhum equipamento cadastrado.
                            </p>
                        )}
                        <div className="flex flex-wrap gap-2">
                            {equipamentos.map((equipamento) => {
                                const pedido = rascunho.equipamentos.find(
                                    (item) => item.id === equipamento.id,
                                );
                                const id = `${idDoCartao}-eq-${equipamento.id}`;
                                const manutencao = estaEmManutencao(
                                    equipamento.manutencao_ate,
                                    rascunho.data,
                                );

                                return (
                                    <div
                                        key={equipamento.id}
                                        className={cn(
                                            'flex min-h-11 flex-wrap items-center gap-2 rounded-lg border px-3 py-1',
                                            pedido &&
                                                'border-primary/40 bg-secondary/40',
                                        )}
                                    >
                                        <Checkbox
                                            id={id}
                                            checked={pedido !== undefined}
                                            onCheckedChange={() =>
                                                alterar({
                                                    ...rascunhoRef.current,
                                                    equipamentos:
                                                        alternarEquipamento(
                                                            rascunhoRef.current
                                                                .equipamentos,
                                                            equipamento.id,
                                                            equipamento.nome,
                                                        ),
                                                })
                                            }
                                            className="size-5"
                                        />
                                        <Label
                                            htmlFor={id}
                                            className="text-sm font-normal"
                                        >
                                            {equipamento.nome}
                                            {manutencao && (
                                                <span className="ml-1 text-xs text-aviso">
                                                    (em manutenção nesta data)
                                                </span>
                                            )}
                                        </Label>
                                        {pedido && (
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    aria-label={`Diminuir ${equipamento.nome}`}
                                                    disabled={
                                                        pedido.quantidade <= 1
                                                    }
                                                    onClick={() =>
                                                        alterar({
                                                            ...rascunhoRef.current,
                                                            equipamentos:
                                                                ajustarQuantidade(
                                                                    rascunhoRef
                                                                        .current
                                                                        .equipamentos,
                                                                    equipamento.id,
                                                                    pedido.quantidade -
                                                                        1,
                                                                    equipamento.quantidade,
                                                                ),
                                                        })
                                                    }
                                                    className="size-11"
                                                >
                                                    <Minus aria-hidden="true" />
                                                </Button>
                                                <span className="w-16 text-center text-sm tabular-nums">
                                                    {pedido.quantidade} de{' '}
                                                    {equipamento.quantidade}
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    aria-label={`Aumentar ${equipamento.nome}`}
                                                    disabled={
                                                        pedido.quantidade >=
                                                        equipamento.quantidade
                                                    }
                                                    onClick={() =>
                                                        alterar({
                                                            ...rascunhoRef.current,
                                                            equipamentos:
                                                                ajustarQuantidade(
                                                                    rascunhoRef
                                                                        .current
                                                                        .equipamentos,
                                                                    equipamento.id,
                                                                    pedido.quantidade +
                                                                        1,
                                                                    equipamento.quantidade,
                                                                ),
                                                        })
                                                    }
                                                    className="size-11"
                                                >
                                                    <Plus aria-hidden="true" />
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </fieldset>

                    {cartao.estado === 'conflito' && (
                        <div
                            role="alert"
                            className="mx-5 mb-4 flex flex-col gap-3 rounded-xl bg-destructive/10 p-4 text-sm"
                        >
                            <p className="flex items-center gap-2 font-semibold text-destructive">
                                <CircleAlert
                                    aria-hidden="true"
                                    className="size-4 shrink-0"
                                />
                                Não dá para aprovar neste horário
                            </p>
                            <ul className="list-disc space-y-1 pl-5">
                                {cartao.conflitos.map((conflito) => (
                                    <li
                                        key={`${conflito.tipo}-${conflito.mensagem}`}
                                    >
                                        {conflito.mensagem}
                                    </li>
                                ))}
                            </ul>
                            {(cartao.sugestoes.espacos.length > 0 ||
                                cartao.sugestoes.horario !== null) && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span>Sugestões:</span>
                                    {cartao.sugestoes.espacos.map((espaco) => (
                                        <Button
                                            key={espaco.id}
                                            type="button"
                                            variant="outline"
                                            className="h-11 rounded-full bg-card"
                                            onClick={() =>
                                                alterar(
                                                    aplicarSugestaoDeEspaco(
                                                        rascunhoRef.current,
                                                        espaco.id,
                                                    ),
                                                    true,
                                                )
                                            }
                                        >
                                            Trocar para {espaco.nome}
                                            {espaco.capacidade !== null
                                                ? ` · ${espaco.capacidade}`
                                                : ''}
                                        </Button>
                                    ))}
                                    {horarioSugerido !== null && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="h-11 rounded-full bg-card"
                                            onClick={() =>
                                                alterar(
                                                    aplicarSugestaoDeHorario(
                                                        rascunhoRef.current,
                                                        horarioSugerido,
                                                    ),
                                                    true,
                                                )
                                            }
                                        >
                                            Horário livre:{' '}
                                            {horarioSugerido.inicio} –{' '}
                                            {horarioSugerido.fim}
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                </>
            ) : (
                <ResumoDaSolicitacao
                    cartao={cartao}
                    espacoNome={espacoDoCartao?.nome}
                />
            )}

            <footer className="flex flex-wrap items-center justify-between gap-3 border-t bg-muted/40 px-5 py-3">
                <div className="flex min-w-0 flex-col gap-1 text-sm text-muted-foreground">
                    {cartao.observacoes && (
                        <p>Obs. do pedido: “{cartao.observacoes}”</p>
                    )}
                    {pendente && (
                        <p role="status" aria-live="polite">
                            <TextoDoSalvamento situacao={salvamento} />
                        </p>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {contato !== null ? (
                        <a
                            href={contato}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={cn(
                                buttonVariants({ variant: 'outline' }),
                                'h-11 gap-2 border-sucesso/40 bg-sucesso-fundo text-sucesso hover:bg-sucesso-fundo',
                            )}
                        >
                            <MessageCircle
                                aria-hidden="true"
                                className="size-4"
                            />
                            Entrar em contato
                            <span className="sr-only">
                                {' '}
                                pelo WhatsApp (abre em nova aba)
                            </span>
                        </a>
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            Telefone inválido: não dá para abrir o WhatsApp.
                        </span>
                    )}

                    {cartao.status === 'aprovada' && (
                        <Link
                            href={rotaDoCalendario({
                                query: { data: cartao.data, visao: 'dia' },
                            })}
                            className={cn(
                                buttonVariants({ variant: 'outline' }),
                                'h-11 gap-2',
                            )}
                        >
                            <CalendarDays
                                aria-hidden="true"
                                className="size-4"
                            />
                            Ver no calendário · {formatarDiaCurto(cartao.data)}
                        </Link>
                    )}

                    {pendente && (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={ocupado}
                                onClick={() => setDialogoDeRecusa(true)}
                                className="h-11 text-destructive"
                            >
                                Recusar
                            </Button>
                            <div className="flex flex-col items-end gap-1">
                                <Button
                                    type="button"
                                    disabled={!aprovacao.pode || ocupado}
                                    aria-busy={acao === 'aprovar'}
                                    aria-describedby={
                                        aprovacao.motivo !== null
                                            ? `${idDoCartao}-aprovar-motivo`
                                            : undefined
                                    }
                                    onClick={() => void aprovar()}
                                    className="h-11 gap-2"
                                >
                                    {acao === 'aprovar' && <Spinner />}
                                    {cartao.estado === 'sem_conflito'
                                        ? 'Aprovar e reservar'
                                        : 'Aprovar'}
                                </Button>
                                {aprovacao.motivo !== null && (
                                    <p
                                        id={`${idDoCartao}-aprovar-motivo`}
                                        className="max-w-xs text-right text-xs text-muted-foreground"
                                    >
                                        {aprovacao.motivo}
                                    </p>
                                )}
                            </div>
                        </>
                    )}
                </div>
            </footer>

            {dialogoDeRecusa && (
                <DialogoDeRecusa
                    enviando={acao === 'recusar'}
                    aoFechar={() => setDialogoDeRecusa(false)}
                    aoConfirmar={(motivo) => void recusar(motivo)}
                />
            )}
        </article>
    );
}

function CampoDeHorario({
    id,
    rotulo,
    tipo,
    valor,
    aoMudar,
}: {
    id: string;
    rotulo: string;
    tipo: 'date' | 'time' | 'number';
    valor: string;
    aoMudar: (valor: string) => void;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-1.5">
            <Label htmlFor={id} className="text-sm">
                {rotulo}
            </Label>
            <Input
                id={id}
                type={tipo}
                min={tipo === 'number' ? 1 : undefined}
                inputMode={tipo === 'number' ? 'numeric' : undefined}
                value={valor}
                onChange={(evento) => aoMudar(evento.target.value)}
                className="h-11 min-w-0 bg-background"
            />
        </div>
    );
}

function SeloDoCartao({ cartao }: { cartao: Cartao }) {
    if (cartao.status === 'aprovada') {
        return (
            <span className="flex items-center gap-1.5 rounded-full bg-sucesso-fundo px-3 py-1.5 text-sm font-semibold text-sucesso">
                <Check aria-hidden="true" className="size-4" />
                Aprovada
            </span>
        );
    }

    if (cartao.status === 'recusada') {
        return (
            <span className="rounded-full bg-muted px-3 py-1.5 text-sm font-semibold text-muted-foreground">
                Recusada
            </span>
        );
    }

    if (cartao.estado === 'sem_conflito') {
        return (
            <span className="flex items-center gap-1.5 rounded-full bg-sucesso-fundo px-3 py-1.5 text-sm font-semibold text-sucesso">
                <Check aria-hidden="true" className="size-4" />
                Sem conflito
            </span>
        );
    }

    if (cartao.estado === 'escolha_um_espaco') {
        return (
            <span className="rounded-full bg-aviso-fundo px-3 py-1.5 text-sm font-semibold text-aviso">
                Escolha um espaço
            </span>
        );
    }

    return (
        <span className="flex items-center gap-1.5 rounded-full bg-destructive/10 px-3 py-1.5 text-sm font-semibold text-destructive">
            <CircleAlert aria-hidden="true" className="size-4" />
            Conflito
        </span>
    );
}

function TextoDoSalvamento({ situacao }: { situacao: Salvamento }) {
    if (situacao === 'salvando') {
        return (
            <span className="flex items-center gap-2">
                <Spinner /> Salvando…
            </span>
        );
    }

    if (situacao === 'pendente') {
        return <>Alterações pendentes…</>;
    }

    if (situacao === 'erro') {
        return (
            <span className="text-destructive">
                Não foi possível salvar. Altere de novo para tentar outra vez.
            </span>
        );
    }

    return <>Alterações salvas.</>;
}

function ResumoDaSolicitacao({
    cartao,
    espacoNome,
}: {
    cartao: Cartao;
    espacoNome: string | undefined;
}) {
    if (cartao.status === 'recusada') {
        return (
            <p className="px-5 pb-4 text-sm text-muted-foreground">
                {cartao.motivo_recusa
                    ? `Motivo: ${cartao.motivo_recusa}`
                    : 'Recusada sem motivo informado.'}
            </p>
        );
    }

    return (
        <p className="px-5 pb-4 text-sm text-muted-foreground">
            {espacoNome ?? 'Espaço'} · {formatarDiaCurto(cartao.data)},{' '}
            {cartao.inicio} – {cartao.fim}
            {cartao.pessoas !== null ? ` · ${cartao.pessoas} pessoas` : ''}
        </p>
    );
}
