import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Plus, Trash2, X } from 'lucide-react';
import { type FormEvent, type KeyboardEvent, useState } from 'react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/Agendamentos/EspacoController';
import InputError from '@/components/input-error';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import {
    CORES_DOS_ESPACOS,
    type CorDoEspaco,
    corDoEspaco,
} from '@/lib/agenda/cores';
import type { HorarioDoEspaco } from '@/lib/agenda/horarios';
import { normalizarBusca } from '@/lib/busca';
import { cn } from '@/lib/utils';
import { index as rota } from '@/routes/agendamentos/espacos';
import { index as rotaDosEquipamentos } from '@/routes/agendamentos/equipamentos';
import type { BloqueioDoEspaco, EspacoFormulario } from '@/types/agendamentos';

type Props = {
    /** Espaço sendo editado, ou null na criação. */
    espaco: EspacoFormulario | null;
    /** Recursos que outros espaços do dono já usam, para virarem chips. */
    recursosSugeridos: string[];
};

/** Segunda primeiro, como a agenda. O `dia_semana` segue a convenção do banco: 0 é domingo. */
const DIAS_DA_SEMANA = [
    { dia_semana: 1, nome: 'Segunda' },
    { dia_semana: 2, nome: 'Terça' },
    { dia_semana: 3, nome: 'Quarta' },
    { dia_semana: 4, nome: 'Quinta' },
    { dia_semana: 5, nome: 'Sexta' },
    { dia_semana: 6, nome: 'Sábado' },
    { dia_semana: 0, nome: 'Domingo' },
];

const LIMITE_DE_RECURSOS = 80;

type LinhaDeDia = {
    dia_semana: number;
    aberto: boolean;
    abre: string;
    fecha: string;
};

/** Valores de campos numéricos ficam como texto, para o input não brigar com o usuário. */
type DadosDoFormulario = {
    nome: string;
    capacidade: string;
    localizacao: string;
    cor: CorDoEspaco;
    recursos_fixos: string[];
    aceita_solicitacoes: boolean;
    antecedencia_min_horas: string;
    duracao_max_min: string;
    intervalo_min: string;
    ativo: boolean;
    dias: LinhaDeDia[];
    bloqueios: BloqueioDoEspaco[];
};

export default function FormularioDeEspaco({
    espaco,
    recursosSugeridos,
}: Props) {
    const formulario = useForm<DadosDoFormulario>(valoresIniciais(espaco));
    const [opcoesDeRecursos, setOpcoesDeRecursos] = useState<string[]>(() =>
        unicos([...recursosSugeridos, ...(espaco?.recursos_fixos ?? [])]),
    );
    const [novoRecurso, setNovoRecurso] = useState<string | null>(null);
    const [confirmandoExclusao, setConfirmandoExclusao] = useState(false);
    const [excluindo, setExcluindo] = useState(false);

    const dados = formulario.data;
    const dataDoErro = (chave: string): string | undefined =>
        (formulario.errors as Record<string, string | undefined>)[chave];

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();

        if (formulario.processing) {
            return;
        }

        formulario.transform((valores) => montarEnvio(valores));

        const opcoes = {
            preserveScroll: true,
            onHttpException: tratamentoDeErro.onHttpException,
            onNetworkError: tratamentoDeErro.onNetworkError,
            onError: tratamentoDeErro.onError,
        };

        if (espaco) {
            formulario.put(update.url({ espaco: espaco.id }), opcoes);
        } else {
            formulario.post(store.url(), opcoes);
        }
    };

    const excluir = () => {
        if (!espaco || excluindo) {
            return;
        }

        router.delete(destroy.url({ espaco: espaco.id }), {
            preserveScroll: true,
            onStart: () => setExcluindo(true),
            onFinish: () => setExcluindo(false),
            onSuccess: () => setConfirmandoExclusao(false),
            ...tratamentoDeErro,
        });
    };

    const atualizarDia = (diaSemana: number, mudanca: Partial<LinhaDeDia>) => {
        formulario.setData(
            'dias',
            dados.dias.map((linha) =>
                linha.dia_semana === diaSemana
                    ? { ...linha, ...mudanca }
                    : linha,
            ),
        );
    };

    const copiarSegunda = () => {
        const segunda = dados.dias.find((linha) => linha.dia_semana === 1);

        if (!segunda?.aberto) {
            return;
        }

        formulario.setData(
            'dias',
            dados.dias.map((linha) =>
                linha.dia_semana === 1
                    ? linha
                    : {
                          ...linha,
                          aberto: true,
                          abre: segunda.abre,
                          fecha: segunda.fecha,
                      },
            ),
        );
    };

    const alternarRecurso = (recurso: string) => {
        const selecionados = dados.recursos_fixos;

        formulario.setData(
            'recursos_fixos',
            selecionados.includes(recurso)
                ? selecionados.filter((item) => item !== recurso)
                : [...selecionados, recurso],
        );
    };

    const adicionarRecurso = () => {
        const nome = (novoRecurso ?? '').trim();

        if (nome === '') {
            return;
        }

        const jaExiste = opcoesDeRecursos.find(
            (opcao) => normalizarBusca(opcao) === normalizarBusca(nome),
        );
        const recurso = jaExiste ?? nome;

        if (jaExiste === undefined) {
            setOpcoesDeRecursos([...opcoesDeRecursos, recurso]);
        }

        if (!dados.recursos_fixos.includes(recurso)) {
            formulario.setData('recursos_fixos', [
                ...dados.recursos_fixos,
                recurso,
            ]);
        }

        setNovoRecurso(null);
    };

    const aoDigitarRecurso = (evento: KeyboardEvent<HTMLInputElement>) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            adicionarRecurso();
        }

        if (evento.key === 'Escape') {
            setNovoRecurso(null);
        }
    };

    const adicionarBloqueio = () => {
        formulario.setData('bloqueios', [
            ...dados.bloqueios,
            { inicio: '', fim: '', motivo: '' },
        ]);
    };

    const atualizarBloqueio = (
        indice: number,
        campo: keyof BloqueioDoEspaco,
        valor: string,
    ) => {
        formulario.setData(
            'bloqueios',
            dados.bloqueios.map((bloqueio, posicao) =>
                posicao === indice ? { ...bloqueio, [campo]: valor } : bloqueio,
            ),
        );
    };

    const removerBloqueio = (indice: number) => {
        formulario.setData(
            'bloqueios',
            dados.bloqueios.filter((_, posicao) => posicao !== indice),
        );
    };

    const segundaAberta = dados.dias.some(
        (linha) => linha.dia_semana === 1 && linha.aberto,
    );

    /**
     * Erros de horário vêm indexados pela posição no envio, que só tem os dias abertos.
     */
    let posicaoNoEnvio = 0;
    const linhasComPosicao = dados.dias.map((linha) => {
        const posicao = linha.aberto ? posicaoNoEnvio : null;

        if (linha.aberto) {
            posicaoNoEnvio += 1;
        }

        return { linha, posicao };
    });

    return (
        <>
            <Head title={espaco ? espaco.nome : 'Novo espaço'} />
            <form onSubmit={enviar} className="flex flex-col gap-10 p-4">
                <CabecalhoDaPagina
                    titulo={espaco ? espaco.nome : 'Novo espaço'}
                    subtitulo={
                        espaco
                            ? 'Dados, regras de reserva, horários e bloqueios do espaço.'
                            : 'Cadastre uma sala ou ambiente que pode ser reservado.'
                    }
                />

                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <div className="flex flex-col gap-6">
                        <section
                            aria-labelledby="secao-dados"
                            className="flex flex-col gap-5 rounded-2xl border bg-card p-6"
                        >
                            <h2
                                id="secao-dados"
                                className="font-serif text-xl font-semibold"
                            >
                                Dados do espaço
                            </h2>

                            <div className="grid gap-2">
                                <Label htmlFor="espaco-nome">Nome</Label>
                                <Input
                                    id="espaco-nome"
                                    className="h-11"
                                    placeholder="Ex.: Salão paroquial"
                                    maxLength={120}
                                    value={dados.nome}
                                    onChange={(evento) =>
                                        formulario.setData(
                                            'nome',
                                            evento.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={formulario.errors.nome} />
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="espaco-capacidade">
                                        Capacidade (pessoas)
                                    </Label>
                                    <Input
                                        id="espaco-capacidade"
                                        className="h-11"
                                        type="number"
                                        inputMode="numeric"
                                        min={1}
                                        max={2000}
                                        value={dados.capacidade}
                                        onChange={(evento) =>
                                            formulario.setData(
                                                'capacidade',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={formulario.errors.capacidade}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="espaco-localizacao">
                                        Localização
                                    </Label>
                                    <Input
                                        id="espaco-localizacao"
                                        className="h-11"
                                        placeholder="Ex.: Térreo"
                                        maxLength={160}
                                        value={dados.localizacao}
                                        onChange={(evento) =>
                                            formulario.setData(
                                                'localizacao',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={formulario.errors.localizacao}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <span
                                    id="titulo-cor"
                                    className="text-sm leading-none font-medium"
                                >
                                    Cor no calendário
                                </span>
                                <div
                                    role="group"
                                    aria-labelledby="titulo-cor"
                                    className="flex flex-wrap gap-1"
                                >
                                    {CORES_DOS_ESPACOS.map((cor) => {
                                        const paleta = corDoEspaco(cor);
                                        const selecionada = dados.cor === cor;

                                        return (
                                            <button
                                                key={cor}
                                                type="button"
                                                aria-label={paleta.nome}
                                                aria-pressed={selecionada}
                                                onClick={() =>
                                                    formulario.setData(
                                                        'cor',
                                                        cor,
                                                    )
                                                }
                                                className="flex size-11 items-center justify-center rounded-full"
                                            >
                                                <span
                                                    className="size-8 rounded-full border-[3px] border-card outline-2 outline-offset-1"
                                                    style={{
                                                        backgroundColor:
                                                            paleta.forte,
                                                        outlineColor:
                                                            selecionada
                                                                ? paleta.forte
                                                                : 'transparent',
                                                    }}
                                                />
                                            </button>
                                        );
                                    })}
                                </div>
                                <InputError message={formulario.errors.cor} />
                            </div>

                            <div className="grid gap-3">
                                <span
                                    id="titulo-recursos"
                                    className="text-sm leading-none font-medium"
                                >
                                    Recursos fixos da sala
                                </span>
                                <p className="text-sm text-muted-foreground">
                                    O que fica sempre aqui. Projetores e itens
                                    que circulam entre salas são cadastrados em{' '}
                                    <Link
                                        href={rotaDosEquipamentos()}
                                        className="font-medium text-primary underline-offset-4 hover:underline"
                                    >
                                        Equipamentos
                                    </Link>
                                    .
                                </p>
                                <div
                                    role="group"
                                    aria-labelledby="titulo-recursos"
                                    className="flex flex-wrap gap-2"
                                >
                                    {opcoesDeRecursos.map((recurso) => {
                                        const selecionado =
                                            dados.recursos_fixos.includes(
                                                recurso,
                                            );

                                        return (
                                            <button
                                                key={recurso}
                                                type="button"
                                                aria-pressed={selecionado}
                                                onClick={() =>
                                                    alternarRecurso(recurso)
                                                }
                                                className={cn(
                                                    'inline-flex min-h-11 items-center gap-1.5 rounded-full border px-4 text-sm transition-colors',
                                                    selecionado
                                                        ? 'border-primary bg-primary/10 font-medium text-primary'
                                                        : 'border-input bg-background text-muted-foreground hover:text-foreground',
                                                )}
                                            >
                                                {selecionado && (
                                                    <Check
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                {recurso}
                                            </button>
                                        );
                                    })}

                                    {novoRecurso === null ? (
                                        <button
                                            type="button"
                                            onClick={() => setNovoRecurso('')}
                                            className="min-h-11 rounded-full border border-dashed border-input px-4 text-sm text-muted-foreground hover:text-foreground"
                                        >
                                            + Novo recurso
                                        </button>
                                    ) : (
                                        <div className="flex w-full items-center gap-2 sm:w-auto">
                                            <Input
                                                aria-label="Nome do novo recurso"
                                                placeholder="Ex.: Ar-condicionado"
                                                maxLength={LIMITE_DE_RECURSOS}
                                                className="h-11 max-w-60"
                                                value={novoRecurso}
                                                autoFocus
                                                onChange={(evento) =>
                                                    setNovoRecurso(
                                                        evento.target.value,
                                                    )
                                                }
                                                onKeyDown={aoDigitarRecurso}
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="min-h-11"
                                                onClick={adicionarRecurso}
                                            >
                                                Adicionar
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-11"
                                                aria-label="Cancelar novo recurso"
                                                onClick={() =>
                                                    setNovoRecurso(null)
                                                }
                                            >
                                                <X />
                                            </Button>
                                        </div>
                                    )}
                                </div>
                                <InputError
                                    message={formulario.errors.recursos_fixos}
                                />
                            </div>
                        </section>

                        <section
                            aria-labelledby="secao-regras"
                            className="flex flex-col gap-5 rounded-2xl border bg-card p-6"
                        >
                            <h2
                                id="secao-regras"
                                className="font-serif text-xl font-semibold"
                            >
                                Regras de reserva
                            </h2>

                            <Label
                                htmlFor="aceita-solicitacoes"
                                className="flex min-h-11 cursor-pointer items-center justify-between gap-4"
                            >
                                <span className="flex flex-col gap-0.5">
                                    <span className="font-medium">
                                        Aparece no formulário de solicitação
                                    </span>
                                    <span className="text-sm font-normal text-muted-foreground">
                                        Pessoas de fora podem pedir este espaço.
                                        Desligado, só a secretaria reserva.
                                    </span>
                                </span>
                                <Switch
                                    id="aceita-solicitacoes"
                                    checked={dados.aceita_solicitacoes}
                                    onCheckedChange={(valor) =>
                                        formulario.setData(
                                            'aceita_solicitacoes',
                                            valor,
                                        )
                                    }
                                />
                            </Label>
                            <InputError
                                message={formulario.errors.aceita_solicitacoes}
                            />

                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="antecedencia-minima">
                                        Antecedência mínima (horas)
                                    </Label>
                                    <Input
                                        id="antecedencia-minima"
                                        className="h-11"
                                        type="number"
                                        inputMode="numeric"
                                        min={0}
                                        value={dados.antecedencia_min_horas}
                                        onChange={(evento) =>
                                            formulario.setData(
                                                'antecedencia_min_horas',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            formulario.errors
                                                .antecedencia_min_horas
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="duracao-maxima">
                                        Duração máxima (min)
                                    </Label>
                                    <Input
                                        id="duracao-maxima"
                                        className="h-11"
                                        type="number"
                                        inputMode="numeric"
                                        min={1}
                                        placeholder="Sem limite"
                                        value={dados.duracao_max_min}
                                        onChange={(evento) =>
                                            formulario.setData(
                                                'duracao_max_min',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            formulario.errors.duracao_max_min
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="intervalo-entre-reservas">
                                        Intervalo entre reservas (min)
                                    </Label>
                                    <Input
                                        id="intervalo-entre-reservas"
                                        className="h-11"
                                        type="number"
                                        inputMode="numeric"
                                        min={0}
                                        value={dados.intervalo_min}
                                        onChange={(evento) =>
                                            formulario.setData(
                                                'intervalo_min',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            formulario.errors.intervalo_min
                                        }
                                    />
                                </div>
                            </div>
                        </section>
                    </div>

                    <div className="flex flex-col gap-6">
                        <section
                            aria-labelledby="secao-horarios"
                            className="flex flex-col gap-4 rounded-2xl border bg-card p-6"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h2
                                    id="secao-horarios"
                                    className="font-serif text-xl font-semibold"
                                >
                                    Horários disponíveis
                                </h2>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="min-h-11 text-primary"
                                    onClick={copiarSegunda}
                                    disabled={!segundaAberta}
                                >
                                    Copiar seg. para todos
                                </Button>
                            </div>

                            <p className="text-sm text-muted-foreground">
                                Dia sem horário fica fechado. Os horários valem
                                para a secretaria e para o formulário de
                                solicitação.
                            </p>

                            <ul className="flex flex-col gap-3">
                                {linhasComPosicao.map(({ linha, posicao }) => {
                                    const nome =
                                        DIAS_DA_SEMANA.find(
                                            (dia) =>
                                                dia.dia_semana ===
                                                linha.dia_semana,
                                        )?.nome ?? '';
                                    const idDoDia = `dia-${linha.dia_semana}`;
                                    const erroAbre =
                                        posicao === null
                                            ? undefined
                                            : dataDoErro(
                                                  `horarios.${posicao}.abre`,
                                              );
                                    const erroFecha =
                                        posicao === null
                                            ? undefined
                                            : dataDoErro(
                                                  `horarios.${posicao}.fecha`,
                                              );

                                    return (
                                        <li
                                            key={linha.dia_semana}
                                            className="grid gap-1"
                                        >
                                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                                                <Label
                                                    htmlFor={idDoDia}
                                                    className="flex min-h-11 w-32 cursor-pointer items-center gap-3 font-medium"
                                                >
                                                    <Checkbox
                                                        id={idDoDia}
                                                        checked={linha.aberto}
                                                        onCheckedChange={(
                                                            valor,
                                                        ) =>
                                                            atualizarDia(
                                                                linha.dia_semana,
                                                                {
                                                                    aberto:
                                                                        valor ===
                                                                        true,
                                                                },
                                                            )
                                                        }
                                                    />
                                                    {nome}
                                                </Label>

                                                {linha.aberto ? (
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Input
                                                            type="time"
                                                            aria-label={`Abre ${nome.toLowerCase()}`}
                                                            className="h-11 w-32"
                                                            value={linha.abre}
                                                            onChange={(
                                                                evento,
                                                            ) =>
                                                                atualizarDia(
                                                                    linha.dia_semana,
                                                                    {
                                                                        abre: evento
                                                                            .target
                                                                            .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <span className="text-muted-foreground">
                                                            até
                                                        </span>
                                                        <Input
                                                            type="time"
                                                            aria-label={`Fecha ${nome.toLowerCase()}`}
                                                            className="h-11 w-32"
                                                            value={linha.fecha}
                                                            onChange={(
                                                                evento,
                                                            ) =>
                                                                atualizarDia(
                                                                    linha.dia_semana,
                                                                    {
                                                                        fecha: evento
                                                                            .target
                                                                            .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        Fechado
                                                    </span>
                                                )}
                                            </div>
                                            <InputError message={erroAbre} />
                                            <InputError message={erroFecha} />
                                        </li>
                                    );
                                })}
                            </ul>
                            <InputError message={dataDoErro('horarios')} />
                        </section>

                        <section
                            aria-labelledby="secao-bloqueios"
                            className="flex flex-col gap-4 rounded-2xl border bg-card p-6"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h2
                                    id="secao-bloqueios"
                                    className="font-serif text-xl font-semibold"
                                >
                                    Bloqueios
                                </h2>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="min-h-11 text-primary"
                                    onClick={adicionarBloqueio}
                                >
                                    <Plus />
                                    Adicionar bloqueio
                                </Button>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Datas em que o espaço não pode ser reservado,
                                como feriados, festas e manutenção.
                            </p>

                            {dados.bloqueios.length === 0 && (
                                <p className="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground">
                                    Nenhum bloqueio cadastrado.
                                </p>
                            )}

                            <ul className="flex flex-col gap-3">
                                {dados.bloqueios.map((bloqueio, indice) => (
                                    <li
                                        key={indice}
                                        className="grid gap-3 rounded-xl border p-4 sm:grid-cols-[1fr_1fr_auto]"
                                    >
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`bloqueio-${indice}-inicio`}
                                            >
                                                Início
                                            </Label>
                                            <Input
                                                id={`bloqueio-${indice}-inicio`}
                                                type="date"
                                                className="h-11"
                                                value={bloqueio.inicio}
                                                onChange={(evento) =>
                                                    atualizarBloqueio(
                                                        indice,
                                                        'inicio',
                                                        evento.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={dataDoErro(
                                                    `bloqueios.${indice}.inicio`,
                                                )}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`bloqueio-${indice}-fim`}
                                            >
                                                Fim
                                            </Label>
                                            <Input
                                                id={`bloqueio-${indice}-fim`}
                                                type="date"
                                                className="h-11"
                                                value={bloqueio.fim}
                                                onChange={(evento) =>
                                                    atualizarBloqueio(
                                                        indice,
                                                        'fim',
                                                        evento.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={dataDoErro(
                                                    `bloqueios.${indice}.fim`,
                                                )}
                                            />
                                        </div>
                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label
                                                htmlFor={`bloqueio-${indice}-motivo`}
                                            >
                                                Motivo
                                            </Label>
                                            <div className="flex items-center gap-2">
                                                <Input
                                                    id={`bloqueio-${indice}-motivo`}
                                                    className="h-11"
                                                    placeholder="Ex.: Festa de Nossa Senhora"
                                                    maxLength={160}
                                                    value={bloqueio.motivo}
                                                    onChange={(evento) =>
                                                        atualizarBloqueio(
                                                            indice,
                                                            'motivo',
                                                            evento.target.value,
                                                        )
                                                    }
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-11 shrink-0 text-destructive hover:text-destructive"
                                                    aria-label={`Remover bloqueio ${indice + 1}`}
                                                    onClick={() =>
                                                        removerBloqueio(indice)
                                                    }
                                                >
                                                    <X />
                                                </Button>
                                            </div>
                                            <InputError
                                                message={dataDoErro(
                                                    `bloqueios.${indice}.motivo`,
                                                )}
                                            />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </div>
                </div>

                <section
                    aria-label="Ativo e ações"
                    className="flex flex-col gap-4 rounded-2xl border bg-card p-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <Label
                        htmlFor="espaco-ativo"
                        className="flex min-h-11 cursor-pointer items-center gap-4"
                    >
                        <Switch
                            id="espaco-ativo"
                            checked={dados.ativo}
                            onCheckedChange={(valor) =>
                                formulario.setData('ativo', valor)
                            }
                        />
                        <span className="flex flex-col gap-0.5">
                            <span className="font-semibold">
                                {dados.ativo
                                    ? 'Espaço ativo'
                                    : 'Espaço inativo'}
                            </span>
                            <span className="text-sm font-normal text-muted-foreground">
                                Desativar tira o espaço das novas reservas. As
                                reservas já feitas continuam.
                            </span>
                        </span>
                    </Label>
                    <InputError message={formulario.errors.ativo} />

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
                        {espaco && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="min-h-11 text-destructive hover:text-destructive"
                                disabled={formulario.processing || excluindo}
                                onClick={() => setConfirmandoExclusao(true)}
                            >
                                <Trash2 />
                                Excluir espaço
                            </Button>
                        )}
                        <Button variant="outline" className="min-h-11" asChild>
                            <Link href={rota()}>Cancelar</Link>
                        </Button>
                        <Button
                            type="submit"
                            className="min-h-11"
                            disabled={formulario.processing}
                        >
                            {formulario.processing && <Spinner />}
                            Salvar
                        </Button>
                    </div>
                </section>
            </form>

            {espaco && (
                <AlertDialog
                    open={confirmandoExclusao}
                    onOpenChange={setConfirmandoExclusao}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Excluir "{espaco.nome}"?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Esta ação não pode ser desfeita. Se o espaço
                                tiver reservas, a exclusão não acontece e você
                                verá o motivo.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={excluindo}>
                                Cancelar
                            </AlertDialogCancel>
                            <AlertDialogAction
                                disabled={excluindo}
                                onClick={(evento) => {
                                    evento.preventDefault();
                                    excluir();
                                }}
                            >
                                {excluindo && <Spinner />}
                                Excluir
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            )}
        </>
    );
}

FormularioDeEspaco.layout = {
    breadcrumbs: [{ title: 'Espaços', href: rota() }],
};

function valoresIniciais(espaco: EspacoFormulario | null): DadosDoFormulario {
    return {
        nome: espaco?.nome ?? '',
        capacidade: espaco?.capacidade?.toString() ?? '',
        localizacao: espaco?.localizacao ?? '',
        cor: espaco?.cor ?? 'bordo',
        recursos_fixos: espaco?.recursos_fixos ?? [],
        aceita_solicitacoes: espaco?.aceita_solicitacoes ?? true,
        antecedencia_min_horas: String(espaco?.antecedencia_min_horas ?? 0),
        duracao_max_min: espaco?.duracao_max_min?.toString() ?? '',
        intervalo_min: String(espaco?.intervalo_min ?? 0),
        ativo: espaco?.ativo ?? true,
        dias: DIAS_DA_SEMANA.map(({ dia_semana }) => {
            const horario: HorarioDoEspaco | undefined = espaco?.horarios.find(
                (item) => item.dia_semana === dia_semana,
            );

            return {
                dia_semana,
                aberto: horario !== undefined,
                abre: horario?.abre.slice(0, 5) ?? '08:00',
                fecha: horario?.fecha.slice(0, 5) ?? '18:00',
            };
        }),
        bloqueios: espaco?.bloqueios ?? [],
    };
}

/** Converte o formulário no corpo que o servidor espera: números de verdade e só os dias abertos. */
function montarEnvio(dados: DadosDoFormulario) {
    return {
        nome: dados.nome.trim(),
        capacidade: numeroOuNulo(dados.capacidade),
        localizacao:
            dados.localizacao.trim() === '' ? null : dados.localizacao.trim(),
        cor: dados.cor,
        recursos_fixos: dados.recursos_fixos,
        aceita_solicitacoes: dados.aceita_solicitacoes,
        antecedencia_min_horas: numeroOuZero(dados.antecedencia_min_horas),
        duracao_max_min: numeroOuNulo(dados.duracao_max_min),
        intervalo_min: numeroOuZero(dados.intervalo_min),
        ativo: dados.ativo,
        horarios: dados.dias
            .filter((linha) => linha.aberto)
            .map((linha) => ({
                dia_semana: linha.dia_semana,
                abre: linha.abre,
                fecha: linha.fecha,
            })),
        bloqueios: dados.bloqueios,
    };
}

function numeroOuNulo(texto: string): number | null {
    const limpo = texto.trim();

    return limpo === '' ? null : Number(limpo);
}

function numeroOuZero(texto: string): number {
    return numeroOuNulo(texto) ?? 0;
}

function unicos(valores: string[]): string[] {
    return [...new Set(valores)];
}
