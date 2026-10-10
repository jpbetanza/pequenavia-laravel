import { CircleAlert, Minus, Plus, Repeat, Trash2, X } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/Agendamentos/ReservaController';
import DisponibilidadeController from '@/actions/App/Http/Controllers/Agendamentos/DisponibilidadeController';
import { tratamentoDeErro } from '@/components/modelos/erro-de-acao';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    type ConflitoDaData,
    datasComConflito,
    fimPadrao,
    horariosEmOrdem,
    resumoDoEquipamento,
    type RespostaDaDisponibilidade,
    sugestoesDosConflitos,
    textoDoAlertaDeConflito,
} from '@/lib/agenda/disponibilidade';
import { corDoEspaco } from '@/lib/agenda/cores';
import { formatarDiaCurto } from '@/lib/agenda/datas';
import {
    ateSugerido,
    datasDaRecorrencia,
    type Repeticao,
    resumoDaRecorrencia,
} from '@/lib/agenda/recorrencia';
import { cn } from '@/lib/utils';
import { enviarJson, ErroHttp, mensagemDoErro } from '@/lib/http';
import { toast } from 'sonner';
import type {
    EquipamentoDoCalendario,
    EspacoDoCalendario,
    GrupoDoCalendario,
    ReservaDoCalendario,
} from '@/types/agendamentos';

/** O que abriu o painel: uma reserva nova (com dia, hora e espaço opcionais) ou uma existente. */
export type AlvoDoPainel =
    | { modo: 'nova'; data: string; hora?: string; espacoId?: string }
    | { modo: 'editar'; reserva: ReservaDoCalendario };

type Props = {
    alvo: AlvoDoPainel;
    espacos: EspacoDoCalendario[];
    grupos: GrupoDoCalendario[];
    equipamentos: EquipamentoDoCalendario[];
    onFechar: () => void;
    /** Chamado depois de salvar ou excluir; a página fecha o painel e recarrega os dados. */
    onSalvo: () => void;
};

type Escopo = 'so_esta' | 'esta_e_proximas';

type Formulario = {
    titulo: string;
    grupoId: string;
    pessoas: string;
    espacoId: string;
    data: string;
    inicio: string;
    fim: string;
    repetir: Repeticao;
    ate: string;
    pulas: string[];
    equipamentos: { id: string; quantidade: number }[];
    observacoes: string;
    escopo: Escopo;
};

type Verificacao = {
    chave: string;
    resposta: RespostaDaDisponibilidade | null;
    erro: string | null;
};

type ConflitosDaData = { data: string; conflitos: ConflitoDaData[] };

type ConflitosDoServidor = {
    chave: string;
    mensagem: string;
    datas: ConflitosDaData[];
};

const SEM_GRUPO = 'nenhum';

const OPCOES_DE_REPETICAO: { valor: Repeticao; rotulo: string }[] = [
    { valor: 'nao', rotulo: 'Não repete' },
    { valor: 'semanal', rotulo: 'Toda semana' },
    { valor: 'mensal', rotulo: 'Todo mês' },
];

const OPCOES_DE_ESCOPO: { valor: Escopo; rotulo: string }[] = [
    { valor: 'so_esta', rotulo: 'Só esta' },
    { valor: 'esta_e_proximas', rotulo: 'Esta e as próximas' },
];

function formularioInicial(
    alvo: AlvoDoPainel,
    espacos: EspacoDoCalendario[],
): Formulario {
    if (alvo.modo === 'editar') {
        const { reserva } = alvo;

        return {
            titulo: reserva.titulo,
            grupoId: reserva.grupo_id ?? '',
            pessoas: reserva.pessoas === null ? '' : String(reserva.pessoas),
            espacoId: reserva.espaco_id,
            data: reserva.data,
            inicio: reserva.inicio,
            fim: reserva.fim,
            repetir: 'nao',
            ate: ateSugerido(reserva.data),
            pulas: [],
            equipamentos: reserva.equipamentos.map((item) => ({
                id: item.id,
                quantidade: item.quantidade,
            })),
            observacoes: reserva.observacoes ?? '',
            escopo: 'so_esta',
        };
    }

    const inicio = alvo.hora ?? '09:00';

    return {
        titulo: '',
        grupoId: '',
        pessoas: '',
        espacoId: alvo.espacoId ?? espacos[0]?.id ?? '',
        data: alvo.data,
        inicio,
        fim: fimPadrao(inicio),
        repetir: 'nao',
        ate: ateSugerido(alvo.data),
        pulas: [],
        equipamentos: [],
        observacoes: '',
        escopo: 'so_esta',
    };
}

/**
 * Painel de reserva (Sheet à direita, tela cheia no celular). Monta só quando aberto,
 * então cada abertura começa com os dados do alvo.
 */
export default function PainelDeReserva({
    alvo,
    espacos,
    grupos,
    equipamentos,
    onFechar,
    onSalvo,
}: Props) {
    const editando = alvo.modo === 'editar';
    const reservaId = alvo.modo === 'editar' ? alvo.reserva.id : null;
    const ehSerie = alvo.modo === 'editar' && alvo.reserva.serie_id !== null;
    const [form, setForm] = useState<Formulario>(() =>
        formularioInicial(alvo, espacos),
    );
    const [verificacao, setVerificacao] = useState<Verificacao | null>(null);
    const [conflitosDoServidor, setConflitosDoServidor] =
        useState<ConflitosDoServidor | null>(null);
    const [salvando, setSalvando] = useState(false);
    const [excluindo, setExcluindo] = useState(false);
    const [confirmandoExclusao, setConfirmandoExclusao] = useState(false);

    // Edição não repete: a mudança vale para a data da reserva (ou para a série a partir dela, no escopo).
    const datas = editando
        ? [form.data]
        : datasDaRecorrencia(
              form.data,
              form.repetir,
              form.repetir === 'nao' ? null : form.ate,
          );
    const consideradas = datas.filter((data) => !form.pulas.includes(data));
    const equipamentosPedidos = form.equipamentos
        .filter((item) => item.quantidade > 0)
        .map(({ id, quantidade }) => ({ id, quantidade }));
    const pessoas = form.pessoas.trim() === '' ? null : Number(form.pessoas);
    const pessoasValidas =
        pessoas === null || (Number.isInteger(pessoas) && pessoas >= 1);
    const horarioValido = horariosEmOrdem(form.inicio, form.fim);
    const verificavel =
        form.espacoId !== '' &&
        horarioValido &&
        pessoasValidas &&
        consideradas.length > 0;
    const chave = verificavel
        ? JSON.stringify({
              espaco_id: form.espacoId,
              datas,
              inicio: form.inicio,
              fim: form.fim,
              equipamentos: equipamentosPedidos,
              ignorar_reserva_id: reservaId,
              pessoas,
          })
        : null;

    useEffect(() => {
        if (chave === null) {
            return;
        }

        // Debounce de 300 ms; a requisição anterior é cancelada quando a chave muda.
        const controlador = new AbortController();
        const temporizador = window.setTimeout(() => {
            enviarJson<RespostaDaDisponibilidade>(
                'POST',
                DisponibilidadeController.url(),
                JSON.parse(chave),
                controlador.signal,
            )
                .then((resposta) => {
                    if (resposta && !controlador.signal.aborted) {
                        setVerificacao({ chave, resposta, erro: null });
                    }
                })
                .catch((erro: unknown) => {
                    if (!controlador.signal.aborted) {
                        setVerificacao({
                            chave,
                            resposta: null,
                            erro: mensagemDoErro(erro),
                        });
                    }
                });
        }, 300);

        return () => {
            window.clearTimeout(temporizador);
            controlador.abort();
        };
    }, [chave]);

    const resultado =
        verificacao !== null && verificacao.chave === chave
            ? verificacao
            : null;
    const verificando = chave !== null && resultado === null;
    const respostaAtual = resultado?.resposta ?? null;
    const erroDaVerificacao = resultado?.erro ?? null;
    const conflitantes = respostaAtual
        ? datasComConflito(respostaAtual.datas, form.pulas)
        : [];
    const chaveDoFormulario = JSON.stringify({ chave, pulas: form.pulas });
    const conflitosDoServidorAtuais =
        conflitosDoServidor?.chave === chaveDoFormulario
            ? conflitosDoServidor
            : null;
    const listaDeConflitos = conflitosDoServidorAtuais
        ? conflitosDoServidorAtuais.datas
        : conflitantes.map((item) => ({
              data: item.data,
              conflitos: item.conflitos,
          }));
    const sugestoes = sugestoesDosConflitos(conflitantes);

    const bloqueio: string | null = !form.titulo.trim()
        ? 'Informe o título'
        : form.espacoId === ''
          ? 'Escolha um espaço'
          : !horarioValido
            ? 'Confira os horários'
            : !pessoasValidas
              ? 'Confira o número de pessoas'
              : consideradas.length === 0
                ? 'Escolha pelo menos uma data'
                : verificando
                  ? 'Verificando disponibilidade…'
                  : erroDaVerificacao !== null
                    ? 'Não foi possível verificar'
                    : listaDeConflitos.length > 0
                      ? 'Resolva o conflito para salvar'
                      : null;

    function alterar<K extends keyof Formulario>(
        campo: K,
        valor: Formulario[K],
    ) {
        setForm((atual) => ({ ...atual, [campo]: valor }));
    }

    function alterarData(data: string) {
        setForm((atual) => ({
            ...atual,
            data,
            ate: atual.ate < data ? ateSugerido(data) : atual.ate,
        }));
    }

    function alternarPulada(data: string, pulada: boolean) {
        setForm((atual) => ({
            ...atual,
            pulas: pulada
                ? [...atual.pulas.filter((item) => item !== data), data]
                : atual.pulas.filter((item) => item !== data),
        }));
    }

    function aplicarSugestaoDeHorario(inicio: string, fim: string) {
        setForm((atual) => ({ ...atual, inicio, fim }));
    }

    function mudarQuantidade(id: string, quantidade: number) {
        setForm((atual) => ({
            ...atual,
            equipamentos: atual.equipamentos.map((item) =>
                item.id === id ? { ...item, quantidade } : item,
            ),
        }));
    }

    function adicionarEquipamento(id: string) {
        setForm((atual) => ({
            ...atual,
            equipamentos: [...atual.equipamentos, { id, quantidade: 1 }],
        }));
    }

    function removerEquipamento(id: string) {
        setForm((atual) => ({
            ...atual,
            equipamentos: atual.equipamentos.filter((item) => item.id !== id),
        }));
    }

    async function salvar(evento: FormEvent<HTMLFormElement>) {
        evento.preventDefault();

        if (bloqueio !== null || salvando) {
            return;
        }

        setSalvando(true);

        const dados = {
            titulo: form.titulo.trim(),
            grupo_id: form.grupoId === '' ? null : form.grupoId,
            pessoas,
            espaco_id: form.espacoId,
            data: form.data,
            inicio: form.inicio,
            fim: form.fim,
            observacoes:
                form.observacoes.trim() === '' ? null : form.observacoes.trim(),
            equipamentos: equipamentosPedidos,
        };
        const chaveDoEnvio = chaveDoFormulario;

        try {
            if (alvo.modo === 'editar') {
                await enviarJson(
                    'PUT',
                    update.url({ reserva: alvo.reserva.id }),
                    {
                        ...dados,
                        escopo: form.escopo,
                    },
                );
                toast.success(
                    ehSerie && form.escopo === 'esta_e_proximas'
                        ? 'Reservas atualizadas.'
                        : 'Reserva atualizada.',
                );
            } else {
                await enviarJson('POST', store.url(), {
                    ...dados,
                    repetir: form.repetir,
                    ...(form.repetir === 'nao' ? {} : { ate: form.ate }),
                    pular: form.pulas.filter((data) => datas.includes(data)),
                });
                toast.success(
                    consideradas.length === 1
                        ? 'Reserva criada.'
                        : `${consideradas.length} reservas criadas.`,
                );
            }

            onSalvo();
        } catch (erro) {
            const conflitos =
                erro instanceof ErroHttp ? erro.corpo?.conflitos : undefined;

            if (
                erro instanceof ErroHttp &&
                erro.status === 422 &&
                Array.isArray(conflitos)
            ) {
                setConflitosDoServidor({
                    chave: chaveDoEnvio,
                    mensagem: erro.message,
                    datas: conflitos as ConflitosDaData[],
                });
            } else if (
                erro instanceof ErroHttp &&
                erro.status === 422 &&
                Object.keys(erro.erros).length > 0
            ) {
                tratamentoDeErro.onError(erro.erros);
            } else {
                toast.error(mensagemDoErro(erro));
            }
        } finally {
            setSalvando(false);
        }
    }

    async function excluir() {
        if (reservaId === null || excluindo) {
            return;
        }

        setExcluindo(true);

        try {
            await enviarJson('DELETE', destroy.url({ reserva: reservaId }), {
                escopo: form.escopo,
            });
            toast.success(
                ehSerie && form.escopo === 'esta_e_proximas'
                    ? 'Reservas excluídas.'
                    : 'Reserva excluída.',
            );
            setConfirmandoExclusao(false);
            onSalvo();
        } catch (erro) {
            toast.error(mensagemDoErro(erro));
        } finally {
            setExcluindo(false);
        }
    }

    const espacoEscolhido = espacos.find(
        (espaco) => espaco.id === form.espacoId,
    );
    const consideradasNaResposta = respostaAtual
        ? respostaAtual.datas.filter((item) => !form.pulas.includes(item.data))
        : null;
    const equipamentosLivres = equipamentos.filter(
        (equipamento) =>
            !form.equipamentos.some((item) => item.id === equipamento.id),
    );

    return (
        <>
            <Sheet
                open
                onOpenChange={(aberto) => {
                    if (!aberto) {
                        onFechar();
                    }
                }}
            >
                <SheetContent className="flex w-full max-w-none flex-col gap-0 p-0 sm:max-w-none md:max-w-[560px]">
                    <SheetHeader className="border-b p-6 pb-4">
                        <SheetTitle className="font-serif text-3xl leading-tight font-semibold">
                            {editando ? 'Editar reserva' : 'Nova reserva'}
                        </SheetTitle>
                        <SheetDescription>
                            Reserve um espaço para uma reunião ou atividade.
                        </SheetDescription>
                    </SheetHeader>

                    <form
                        id="formulario-da-reserva"
                        onSubmit={salvar}
                        className="flex flex-1 flex-col gap-5 overflow-y-auto p-6"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="reserva-titulo">Título</Label>
                            <Input
                                id="reserva-titulo"
                                className="h-11"
                                maxLength={160}
                                placeholder="Ex.: Reunião da Pastoral Familiar"
                                value={form.titulo}
                                onChange={(evento) =>
                                    alterar('titulo', evento.target.value)
                                }
                                required
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="reserva-grupo">
                                    Grupo ou pastoral
                                </Label>
                                <Select
                                    value={
                                        form.grupoId === ''
                                            ? SEM_GRUPO
                                            : form.grupoId
                                    }
                                    onValueChange={(valor) =>
                                        alterar(
                                            'grupoId',
                                            valor === SEM_GRUPO ? '' : valor,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="reserva-grupo"
                                        className="h-11 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={SEM_GRUPO}>
                                            Nenhum
                                        </SelectItem>
                                        {grupos.map((grupo) => (
                                            <SelectItem
                                                key={grupo.id}
                                                value={grupo.id}
                                            >
                                                {grupo.nome}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="reserva-pessoas">
                                    Pessoas previstas
                                </Label>
                                <Input
                                    id="reserva-pessoas"
                                    className="h-11"
                                    inputMode="numeric"
                                    placeholder="Opcional"
                                    value={form.pessoas}
                                    onChange={(evento) =>
                                        alterar(
                                            'pessoas',
                                            evento.target.value.replace(
                                                /\D/g,
                                                '',
                                            ),
                                        )
                                    }
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="reserva-espaco">Espaço</Label>
                            <Select
                                value={form.espacoId}
                                onValueChange={(valor) =>
                                    alterar('espacoId', valor)
                                }
                            >
                                <SelectTrigger
                                    id="reserva-espaco"
                                    className="h-11 w-full"
                                >
                                    <SelectValue placeholder="Escolha o espaço" />
                                </SelectTrigger>
                                <SelectContent>
                                    {espacos.map((espaco) => (
                                        <SelectItem
                                            key={espaco.id}
                                            value={espaco.id}
                                        >
                                            <span
                                                aria-hidden="true"
                                                className="inline-block size-2.5 rounded-[3px]"
                                                style={{
                                                    background: corDoEspaco(
                                                        espaco.cor,
                                                    ).forte,
                                                }}
                                            />
                                            {espaco.nome}
                                            {espaco.capacidade !== null &&
                                                ` · até ${espaco.capacidade} pessoas`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {espacoEscolhido && (
                                <p className="text-xs text-muted-foreground">
                                    {espacoEscolhido.capacidade !== null
                                        ? `Capacidade de ${espacoEscolhido.capacidade} pessoas`
                                        : 'Sem capacidade cadastrada'}
                                    {' · '}
                                    {espacoEscolhido.recursos_fixos.length > 0
                                        ? espacoEscolhido.recursos_fixos.join(
                                              ', ',
                                          )
                                        : 'sem recursos fixos'}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-[1.4fr_1fr_1fr] gap-3">
                            <div className="grid min-w-0 gap-2">
                                <Label htmlFor="reserva-data">Data</Label>
                                <Input
                                    id="reserva-data"
                                    type="date"
                                    className="h-11 min-w-0"
                                    value={form.data}
                                    onChange={(evento) =>
                                        alterarData(evento.target.value)
                                    }
                                    required
                                />
                            </div>
                            <div className="grid min-w-0 gap-2">
                                <Label htmlFor="reserva-inicio">Início</Label>
                                <Input
                                    id="reserva-inicio"
                                    type="time"
                                    className="h-11 min-w-0"
                                    value={form.inicio}
                                    onChange={(evento) => {
                                        const inicio = evento.target.value;

                                        setForm((atual) => ({
                                            ...atual,
                                            inicio,
                                            fim: horariosEmOrdem(
                                                inicio,
                                                atual.fim,
                                            )
                                                ? atual.fim
                                                : fimPadrao(inicio),
                                        }));
                                    }}
                                    required
                                />
                            </div>
                            <div className="grid min-w-0 gap-2">
                                <Label htmlFor="reserva-fim">Fim</Label>
                                <Input
                                    id="reserva-fim"
                                    type="time"
                                    className="h-11 min-w-0"
                                    value={form.fim}
                                    onChange={(evento) =>
                                        alterar('fim', evento.target.value)
                                    }
                                    required
                                />
                            </div>
                        </div>
                        {!horarioValido &&
                            form.inicio !== '' &&
                            form.fim !== '' && (
                                <p className="-mt-3 text-sm text-destructive">
                                    O horário final precisa ser depois do
                                    início.
                                </p>
                            )}

                        {!editando && (
                            <fieldset className="grid gap-3">
                                <legend className="mb-1 text-sm font-semibold">
                                    Repetir
                                </legend>
                                <div
                                    role="group"
                                    aria-label="Repetição"
                                    className="flex rounded-xl bg-secondary p-1"
                                >
                                    {OPCOES_DE_REPETICAO.map((opcao) => (
                                        <button
                                            key={opcao.valor}
                                            type="button"
                                            aria-pressed={
                                                form.repetir === opcao.valor
                                            }
                                            onClick={() =>
                                                alterar('repetir', opcao.valor)
                                            }
                                            className={cn(
                                                'flex min-h-11 flex-1 items-center justify-center rounded-lg px-2 text-sm transition-colors',
                                                form.repetir === opcao.valor
                                                    ? 'bg-card font-semibold text-foreground shadow-xs'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            {opcao.rotulo}
                                        </button>
                                    ))}
                                </div>

                                {form.repetir !== 'nao' && (
                                    <div className="grid gap-3">
                                        <div className="grid gap-2">
                                            <Label htmlFor="reserva-ate">
                                                Até
                                            </Label>
                                            <Input
                                                id="reserva-ate"
                                                type="date"
                                                className="h-11"
                                                min={form.data}
                                                value={form.ate}
                                                onChange={(evento) =>
                                                    alterar(
                                                        'ate',
                                                        evento.target.value,
                                                    )
                                                }
                                                required
                                            />
                                        </div>
                                        <p className="text-sm text-foreground/80">
                                            {resumoDaRecorrencia(
                                                form.data,
                                                form.repetir,
                                                form.ate,
                                            )}
                                        </p>
                                        <ListaDeDatas
                                            datas={datas}
                                            pulas={form.pulas}
                                            conflitantes={conflitantes.map(
                                                (item) => item.data,
                                            )}
                                            podePular={datas.length > 1}
                                            aoMudar={alternarPulada}
                                        />
                                    </div>
                                )}
                            </fieldset>
                        )}

                        {editando && ehSerie && (
                            <fieldset className="grid gap-2">
                                <legend className="mb-1 text-sm font-semibold">
                                    Esta mudança vale para
                                </legend>
                                <EscolhaDeEscopo
                                    valor={form.escopo}
                                    aoMudar={(escopo) =>
                                        alterar('escopo', escopo)
                                    }
                                />
                            </fieldset>
                        )}

                        {listaDeConflitos.length > 0 && (
                            <div
                                role="alert"
                                className="grid gap-3 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm"
                            >
                                <p className="flex items-start gap-2 font-semibold text-destructive">
                                    <CircleAlert
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 shrink-0"
                                    />
                                    {textoDoAlertaDeConflito(
                                        listaDeConflitos.length,
                                        consideradas.length,
                                    )}
                                </p>
                                {conflitosDoServidorAtuais && (
                                    <p className="text-muted-foreground">
                                        {conflitosDoServidorAtuais.mensagem}
                                    </p>
                                )}
                                <ul className="grid gap-2">
                                    {listaDeConflitos.map((item) => (
                                        <li
                                            key={item.data}
                                            className="grid gap-1"
                                        >
                                            <span>
                                                <span className="font-semibold">
                                                    {formatarDiaCurto(
                                                        item.data,
                                                    )}
                                                    :
                                                </span>{' '}
                                                {item.conflitos
                                                    .map(
                                                        (conflito) =>
                                                            conflito.mensagem,
                                                    )
                                                    .join(' ')}
                                            </span>
                                            {consideradas.length > 1 && (
                                                <button
                                                    type="button"
                                                    className="min-h-11 w-fit font-semibold text-primary underline-offset-4 hover:underline"
                                                    onClick={() =>
                                                        alternarPulada(
                                                            item.data,
                                                            true,
                                                        )
                                                    }
                                                >
                                                    Pular esta data
                                                </button>
                                            )}
                                        </li>
                                    ))}
                                </ul>

                                {(sugestoes.espacos.some(
                                    (espaco) => espaco.id !== form.espacoId,
                                ) ||
                                    sugestoes.horarios.length > 0) && (
                                    <div className="grid gap-2">
                                        <span className="text-muted-foreground">
                                            Sugestões que cabem:
                                        </span>
                                        <div className="flex flex-wrap gap-2">
                                            {sugestoes.espacos
                                                .filter(
                                                    (espaco) =>
                                                        espaco.id !==
                                                        form.espacoId,
                                                )
                                                .map((espaco) => (
                                                    <button
                                                        key={espaco.id}
                                                        type="button"
                                                        onClick={() =>
                                                            alterar(
                                                                'espacoId',
                                                                espaco.id,
                                                            )
                                                        }
                                                        className="min-h-11 rounded-full border bg-card px-4 text-sm hover:bg-secondary"
                                                    >
                                                        Usar {espaco.nome}
                                                        {espaco.capacidade !==
                                                            null &&
                                                            ` · ${espaco.capacidade}`}
                                                    </button>
                                                ))}
                                            {sugestoes.horarios.map(
                                                (horario) => (
                                                    <button
                                                        key={`${horario.inicio}-${horario.fim}`}
                                                        type="button"
                                                        onClick={() =>
                                                            aplicarSugestaoDeHorario(
                                                                horario.inicio,
                                                                horario.fim,
                                                            )
                                                        }
                                                        className="min-h-11 rounded-full border bg-card px-4 text-sm hover:bg-secondary"
                                                    >
                                                        Usar {horario.inicio}–
                                                        {horario.fim}
                                                    </button>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {verificando && (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Spinner />
                                Verificando disponibilidade…
                            </p>
                        )}
                        {erroDaVerificacao !== null && (
                            <p className="text-sm text-destructive">
                                {erroDaVerificacao}
                            </p>
                        )}
                        {respostaAtual !== null &&
                            listaDeConflitos.length === 0 &&
                            consideradas.length > 0 && (
                                <p className="text-sm text-sucesso">
                                    Sem conflito em{' '}
                                    {consideradas.length === 1
                                        ? 'esta data'
                                        : `as ${consideradas.length} datas`}
                                    .
                                </p>
                            )}

                        <fieldset className="grid gap-3">
                            <legend className="mb-1 text-sm font-semibold">
                                Equipamentos
                            </legend>

                            {form.equipamentos.map((item) => {
                                const equipamento = equipamentos.find(
                                    (candidato) => candidato.id === item.id,
                                );

                                if (!equipamento) {
                                    return null;
                                }

                                const resumo = consideradasNaResposta
                                    ? resumoDoEquipamento(
                                          equipamento.nome,
                                          equipamento.quantidade,
                                          consideradasNaResposta,
                                      )
                                    : null;

                                return (
                                    <div
                                        key={item.id}
                                        className="flex items-center gap-3 rounded-xl border bg-card p-3"
                                    >
                                        <span className="flex min-w-0 flex-1 flex-col leading-tight">
                                            <span className="font-semibold">
                                                {equipamento.nome}
                                            </span>
                                            <span
                                                className={cn(
                                                    'text-xs',
                                                    resumo?.tom === 'alerta'
                                                        ? 'text-destructive'
                                                        : 'text-sucesso',
                                                )}
                                            >
                                                {resumo?.texto ??
                                                    'Verificando…'}
                                            </span>
                                        </span>
                                        <span className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                className="size-11"
                                                aria-label={`Menos ${equipamento.nome}`}
                                                disabled={item.quantidade <= 1}
                                                onClick={() =>
                                                    mudarQuantidade(
                                                        item.id,
                                                        item.quantidade - 1,
                                                    )
                                                }
                                            >
                                                <Minus aria-hidden="true" />
                                            </Button>
                                            <span
                                                aria-live="polite"
                                                className="min-w-6 text-center font-semibold"
                                            >
                                                {item.quantidade}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                className="size-11"
                                                aria-label={`Mais ${equipamento.nome}`}
                                                disabled={
                                                    item.quantidade >=
                                                    equipamento.quantidade
                                                }
                                                onClick={() =>
                                                    mudarQuantidade(
                                                        item.id,
                                                        item.quantidade + 1,
                                                    )
                                                }
                                            >
                                                <Plus aria-hidden="true" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-11"
                                                aria-label={`Remover ${equipamento.nome}`}
                                                onClick={() =>
                                                    removerEquipamento(item.id)
                                                }
                                            >
                                                <X aria-hidden="true" />
                                            </Button>
                                        </span>
                                    </div>
                                );
                            })}

                            {equipamentos.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Nenhum equipamento cadastrado.
                                </p>
                            ) : (
                                equipamentosLivres.length > 0 && (
                                    <Select
                                        value=""
                                        onValueChange={(id) =>
                                            adicionarEquipamento(id)
                                        }
                                    >
                                        <SelectTrigger
                                            aria-label="Adicionar equipamento"
                                            className="h-11 w-full sm:w-72"
                                        >
                                            <SelectValue placeholder="+ Adicionar equipamento" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {equipamentosLivres.map(
                                                (equipamento) => {
                                                    const emManutencao =
                                                        equipamento.manutencao_ate !==
                                                            null &&
                                                        equipamento.manutencao_ate >=
                                                            form.data;

                                                    return (
                                                        <SelectItem
                                                            key={equipamento.id}
                                                            value={
                                                                equipamento.id
                                                            }
                                                            disabled={
                                                                emManutencao
                                                            }
                                                        >
                                                            {equipamento.nome}
                                                            {emManutencao
                                                                ? ' · em manutenção'
                                                                : ` · ${equipamento.quantidade} no total`}
                                                        </SelectItem>
                                                    );
                                                },
                                            )}
                                        </SelectContent>
                                    </Select>
                                )
                            )}
                        </fieldset>

                        <div className="grid gap-2">
                            <Label htmlFor="reserva-observacoes">
                                Observações
                            </Label>
                            <Textarea
                                id="reserva-observacoes"
                                rows={2}
                                maxLength={2000}
                                placeholder="Opcional"
                                value={form.observacoes}
                                onChange={(evento) =>
                                    alterar('observacoes', evento.target.value)
                                }
                            />
                        </div>
                    </form>

                    <SheetFooter className="flex-row items-center justify-between gap-2 border-t bg-muted/40 p-4">
                        {editando ? (
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-11 text-destructive hover:text-destructive"
                                onClick={() => setConfirmandoExclusao(true)}
                            >
                                <Trash2 aria-hidden="true" />
                                Excluir
                            </Button>
                        ) : (
                            <span />
                        )}
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="h-11"
                                onClick={onFechar}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                form="formulario-da-reserva"
                                className="h-11"
                                disabled={bloqueio !== null || salvando}
                            >
                                {salvando
                                    ? 'Salvando…'
                                    : (bloqueio ??
                                      (editando
                                          ? 'Salvar alterações'
                                          : 'Salvar reserva'))}
                            </Button>
                        </div>
                    </SheetFooter>
                </SheetContent>
            </Sheet>

            <AlertDialog
                open={confirmandoExclusao}
                onOpenChange={setConfirmandoExclusao}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Excluir reserva?</AlertDialogTitle>
                        <AlertDialogDescription>
                            {ehSerie && form.escopo === 'esta_e_proximas'
                                ? 'Serão excluídas esta reserva e as próximas da série.'
                                : ehSerie
                                  ? 'Só esta reserva será excluída. As outras da série continuam.'
                                  : 'A reserva sai da agenda.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={excluindo}>
                            Cancelar
                        </AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            disabled={excluindo}
                            onClick={(evento) => {
                                evento.preventDefault();
                                void excluir();
                            }}
                        >
                            {excluindo ? 'Excluindo…' : 'Excluir'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

function EscolhaDeEscopo({
    valor,
    aoMudar,
}: {
    valor: Escopo;
    aoMudar: (escopo: Escopo) => void;
}) {
    return (
        <div
            role="group"
            aria-label="Escopo da mudança"
            className="flex rounded-xl bg-secondary p-1"
        >
            {OPCOES_DE_ESCOPO.map((opcao) => (
                <button
                    key={opcao.valor}
                    type="button"
                    aria-pressed={valor === opcao.valor}
                    onClick={() => aoMudar(opcao.valor)}
                    className={cn(
                        'flex min-h-11 flex-1 items-center justify-center rounded-lg px-2 text-sm transition-colors',
                        valor === opcao.valor
                            ? 'bg-card font-semibold text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {opcao.rotulo}
                </button>
            ))}
        </div>
    );
}

function ListaDeDatas({
    datas,
    pulas,
    conflitantes,
    podePular,
    aoMudar,
}: {
    datas: string[];
    pulas: string[];
    conflitantes: string[];
    podePular: boolean;
    aoMudar: (data: string, pulada: boolean) => void;
}) {
    return (
        <Collapsible>
            <CollapsibleTrigger className="flex min-h-11 items-center gap-2 text-sm font-semibold text-primary">
                <Repeat aria-hidden="true" className="size-4" />
                Ver as {datas.length} datas
            </CollapsibleTrigger>
            <CollapsibleContent>
                <ul className="mt-2 grid gap-1 rounded-xl border bg-card p-2">
                    {datas.map((data) => {
                        const pulada = pulas.includes(data);
                        const temConflito =
                            conflitantes.includes(data) && !pulada;

                        return (
                            <li
                                key={data}
                                className="flex flex-wrap items-center justify-between gap-2 px-2"
                            >
                                <span
                                    className={cn(
                                        'text-sm',
                                        pulada &&
                                            'text-muted-foreground line-through',
                                    )}
                                >
                                    {formatarDiaCurto(data)}
                                </span>
                                {temConflito && (
                                    <span className="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive">
                                        Conflito
                                    </span>
                                )}
                                {podePular && (
                                    <Label className="flex min-h-11 items-center gap-2 text-sm font-normal">
                                        <Checkbox
                                            checked={pulada}
                                            onCheckedChange={(valor) =>
                                                aoMudar(data, valor === true)
                                            }
                                        />
                                        Pular esta data
                                    </Label>
                                )}
                            </li>
                        );
                    })}
                </ul>
            </CollapsibleContent>
        </Collapsible>
    );
}
