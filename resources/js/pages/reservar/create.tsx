import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import InputError from '@/components/input-error';
import CampoDoFormulario from '@/components/reservar/campo-do-formulario';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import {
    dicasDoEspaco,
    mascararTelefone,
    QUALQUER_ESPACO,
} from '@/lib/agenda/formulario-publico';
import { store } from '@/routes/reservar';
import type { PropsDoFormularioPublico } from '@/types/agendamentos';

type Dados = {
    grupo_id: string;
    nome: string;
    telefone: string;
    titulo: string;
    data: string;
    inicio: string;
    fim: string;
    espaco_id: string;
    pessoas: string;
    equipamentos: string[];
    observacoes: string;
    /** Campo armadilha: fica escondido e tem que chegar vazio. */
    site: string;
};

const CAMPO_DE_ERRO = '[aria-invalid="true"]';

export default function Create({
    agenda,
    hoje,
    grupos,
    espacos,
    equipamentos,
}: PropsDoFormularioPublico) {
    const form = useForm<Dados>({
        grupo_id: '',
        nome: '',
        telefone: '',
        titulo: '',
        data: '',
        inicio: '',
        fim: '',
        espaco_id: QUALQUER_ESPACO,
        pessoas: '',
        equipamentos: [],
        observacoes: '',
        site: '',
    });

    const erros = form.errors as Record<string, string | undefined>;
    const erroDeEquipamento =
        erros.equipamentos ??
        Object.entries(erros).find(([campo]) =>
            campo.startsWith('equipamentos.'),
        )?.[1];
    const espacoEscolhido = espacos.find(
        (espaco) => espaco.id === form.data.espaco_id,
    );
    const dicas = espacoEscolhido ? dicasDoEspaco(espacoEscolhido) : [];

    function enviar(evento: FormEvent<HTMLFormElement>) {
        evento.preventDefault();

        if (form.processing) {
            return;
        }

        form.transform((dados) => ({
            ...dados,
            nome: dados.nome.trim(),
            titulo: dados.titulo.trim(),
            telefone: dados.telefone.replace(/\D/g, ''),
            espaco_id:
                dados.espaco_id === QUALQUER_ESPACO ? null : dados.espaco_id,
            pessoas: dados.pessoas.trim() === '' ? null : Number(dados.pessoas),
            observacoes:
                dados.observacoes.trim() === ''
                    ? null
                    : dados.observacoes.trim(),
        }));

        form.post(store.url(agenda.slug), {
            preserveScroll: true,
            onError: () => {
                document.querySelector<HTMLElement>(CAMPO_DE_ERRO)?.focus();
            },
        });
    }

    function marcarEquipamento(id: string, marcado: boolean) {
        form.setData(
            'equipamentos',
            marcado
                ? [...form.data.equipamentos, id]
                : form.data.equipamentos.filter((item) => item !== id),
        );
    }

    return (
        <>
            <Head title="Solicitar uma sala" />

            <form
                onSubmit={enviar}
                noValidate
                className="relative flex flex-col gap-10"
            >
                {/* Honeypot: fora da tela, fora da ordem de tabulação e escondido dos leitores de tela. */}
                <div
                    aria-hidden="true"
                    className="absolute top-0 -left-[10000px] h-px w-px overflow-hidden"
                >
                    <label htmlFor="site">Deixe este campo em branco</label>
                    <input
                        id="site"
                        name="site"
                        type="text"
                        tabIndex={-1}
                        autoComplete="off"
                        value={form.data.site}
                        onChange={(evento) =>
                            form.setData('site', evento.target.value)
                        }
                    />
                </div>

                <fieldset className="flex flex-col gap-5">
                    <legend className="mb-1 text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                        Quem está pedindo
                    </legend>

                    <CampoDoFormulario
                        id="grupo"
                        rotulo="Pastoral ou grupo"
                        erro={erros.grupo_id}
                    >
                        <Select
                            value={form.data.grupo_id}
                            onValueChange={(valor) =>
                                form.setData('grupo_id', valor)
                            }
                        >
                            <SelectTrigger
                                id="grupo"
                                aria-invalid={erros.grupo_id ? true : undefined}
                                aria-describedby={
                                    erros.grupo_id ? 'grupo-erro' : undefined
                                }
                                className="h-12 w-full rounded-xl bg-card text-base"
                            >
                                <SelectValue placeholder="Escolha a pastoral ou grupo" />
                            </SelectTrigger>
                            <SelectContent>
                                {grupos.map((grupo) => (
                                    <SelectItem key={grupo.id} value={grupo.id}>
                                        {grupo.nome}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {grupos.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Nenhuma pastoral ou grupo está disponível para
                                pedidos no momento.
                            </p>
                        )}
                    </CampoDoFormulario>

                    <CampoDoFormulario
                        id="nome"
                        rotulo="Seu nome"
                        erro={erros.nome}
                    >
                        <Input
                            id="nome"
                            autoComplete="name"
                            value={form.data.nome}
                            aria-invalid={erros.nome ? true : undefined}
                            aria-describedby={
                                erros.nome ? 'nome-erro' : undefined
                            }
                            onChange={(evento) =>
                                form.setData('nome', evento.target.value)
                            }
                            className="h-12 rounded-xl bg-card text-base"
                        />
                    </CampoDoFormulario>

                    <CampoDoFormulario
                        id="telefone"
                        rotulo="Telefone (WhatsApp)"
                        erro={erros.telefone}
                        dica={
                            <p
                                id="telefone-dica"
                                className="text-sm text-muted-foreground"
                            >
                                Com DDD. A secretaria responde por este número.
                            </p>
                        }
                    >
                        <Input
                            id="telefone"
                            type="tel"
                            inputMode="tel"
                            autoComplete="tel"
                            placeholder="(11) 90000-0000"
                            value={form.data.telefone}
                            aria-invalid={erros.telefone ? true : undefined}
                            aria-describedby={
                                erros.telefone
                                    ? 'telefone-dica telefone-erro'
                                    : 'telefone-dica'
                            }
                            onChange={(evento) =>
                                form.setData(
                                    'telefone',
                                    mascararTelefone(evento.target.value),
                                )
                            }
                            className="h-12 rounded-xl bg-card text-base"
                        />
                    </CampoDoFormulario>
                </fieldset>

                <fieldset className="flex flex-col gap-5">
                    <legend className="mb-1 text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                        O que vocês precisam
                    </legend>

                    <CampoDoFormulario
                        id="titulo"
                        rotulo="Atividade"
                        erro={erros.titulo}
                    >
                        <Input
                            id="titulo"
                            placeholder="Ex.: Ensaio do coral"
                            value={form.data.titulo}
                            aria-invalid={erros.titulo ? true : undefined}
                            aria-describedby={
                                erros.titulo ? 'titulo-erro' : undefined
                            }
                            onChange={(evento) =>
                                form.setData('titulo', evento.target.value)
                            }
                            className="h-12 rounded-xl bg-card text-base"
                        />
                    </CampoDoFormulario>

                    <CampoDoFormulario
                        id="data"
                        rotulo="Data"
                        erro={erros.data}
                    >
                        <Input
                            id="data"
                            type="date"
                            min={hoje}
                            value={form.data.data}
                            aria-invalid={erros.data ? true : undefined}
                            aria-describedby={
                                erros.data ? 'data-erro' : undefined
                            }
                            onChange={(evento) =>
                                form.setData('data', evento.target.value)
                            }
                            className="h-12 rounded-xl bg-card text-base"
                        />
                    </CampoDoFormulario>

                    <div className="grid grid-cols-2 gap-4">
                        <CampoDoFormulario
                            id="inicio"
                            rotulo="Das"
                            erro={erros.inicio}
                        >
                            <Input
                                id="inicio"
                                type="time"
                                value={form.data.inicio}
                                aria-invalid={erros.inicio ? true : undefined}
                                aria-describedby={
                                    erros.inicio ? 'inicio-erro' : undefined
                                }
                                onChange={(evento) =>
                                    form.setData('inicio', evento.target.value)
                                }
                                className="h-12 min-w-0 rounded-xl bg-card text-base"
                            />
                        </CampoDoFormulario>
                        <CampoDoFormulario
                            id="fim"
                            rotulo="Até"
                            erro={erros.fim}
                        >
                            <Input
                                id="fim"
                                type="time"
                                value={form.data.fim}
                                aria-invalid={erros.fim ? true : undefined}
                                aria-describedby={
                                    erros.fim ? 'fim-erro' : undefined
                                }
                                onChange={(evento) =>
                                    form.setData('fim', evento.target.value)
                                }
                                className="h-12 min-w-0 rounded-xl bg-card text-base"
                            />
                        </CampoDoFormulario>
                    </div>

                    <CampoDoFormulario
                        id="espaco"
                        rotulo="Espaço desejado"
                        erro={erros.espaco_id}
                        dica={
                            <>
                                {dicas.map((dica) => (
                                    <p
                                        key={dica}
                                        className="text-sm text-muted-foreground"
                                    >
                                        {dica}
                                    </p>
                                ))}
                                {dicas.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Pode escolher “Qualquer espaço
                                        disponível”.
                                    </p>
                                )}
                            </>
                        }
                    >
                        <Select
                            value={form.data.espaco_id}
                            onValueChange={(valor) =>
                                form.setData('espaco_id', valor)
                            }
                        >
                            <SelectTrigger
                                id="espaco"
                                aria-invalid={
                                    erros.espaco_id ? true : undefined
                                }
                                aria-describedby={
                                    erros.espaco_id ? 'espaco-erro' : undefined
                                }
                                className="h-12 w-full rounded-xl bg-card text-base"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={QUALQUER_ESPACO}>
                                    Qualquer espaço disponível
                                </SelectItem>
                                {espacos.map((espaco) => (
                                    <SelectItem
                                        key={espaco.id}
                                        value={espaco.id}
                                    >
                                        {espaco.nome}
                                        {espaco.capacidade !== null
                                            ? ` · ${espaco.capacidade} pessoas`
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </CampoDoFormulario>

                    <CampoDoFormulario
                        id="pessoas"
                        rotulo={
                            <>
                                Quantas pessoas?{' '}
                                <span className="font-normal text-muted-foreground">
                                    (opcional)
                                </span>
                            </>
                        }
                        erro={erros.pessoas}
                    >
                        <Input
                            id="pessoas"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            value={form.data.pessoas}
                            aria-invalid={erros.pessoas ? true : undefined}
                            aria-describedby={
                                erros.pessoas ? 'pessoas-erro' : undefined
                            }
                            onChange={(evento) =>
                                form.setData('pessoas', evento.target.value)
                            }
                            className="h-12 max-w-40 rounded-xl bg-card text-base"
                        />
                    </CampoDoFormulario>

                    {equipamentos.length > 0 && (
                        <fieldset
                            className="flex flex-col gap-1"
                            aria-describedby={
                                erroDeEquipamento
                                    ? 'equipamentos-erro'
                                    : undefined
                            }
                        >
                            <legend className="mb-2 text-sm font-semibold">
                                Precisa de equipamento?{' '}
                                <span className="font-normal text-muted-foreground">
                                    (opcional)
                                </span>
                            </legend>
                            {equipamentos.map((equipamento) => {
                                const id = `equipamento-${equipamento.id}`;

                                return (
                                    <div
                                        key={equipamento.id}
                                        className="flex min-h-11 items-center gap-3"
                                    >
                                        <Checkbox
                                            id={id}
                                            checked={form.data.equipamentos.includes(
                                                equipamento.id,
                                            )}
                                            onCheckedChange={(marcado) =>
                                                marcarEquipamento(
                                                    equipamento.id,
                                                    marcado === true,
                                                )
                                            }
                                            className="size-5"
                                        />
                                        <Label
                                            htmlFor={id}
                                            className="min-h-11 flex-1 items-center text-base font-normal"
                                        >
                                            {equipamento.nome}
                                        </Label>
                                    </div>
                                );
                            })}
                            <InputError
                                id="equipamentos-erro"
                                message={erroDeEquipamento}
                                className="text-destructive"
                            />
                        </fieldset>
                    )}

                    <CampoDoFormulario
                        id="observacoes"
                        rotulo={
                            <>
                                Observações{' '}
                                <span className="font-normal text-muted-foreground">
                                    (opcional)
                                </span>
                            </>
                        }
                        erro={erros.observacoes}
                    >
                        <Textarea
                            id="observacoes"
                            rows={3}
                            placeholder="Ex.: precisamos do piano"
                            value={form.data.observacoes}
                            aria-invalid={erros.observacoes ? true : undefined}
                            aria-describedby={
                                erros.observacoes
                                    ? 'observacoes-erro'
                                    : undefined
                            }
                            onChange={(evento) =>
                                form.setData('observacoes', evento.target.value)
                            }
                            className="rounded-xl bg-card text-base"
                        />
                    </CampoDoFormulario>
                </fieldset>

                <div className="flex flex-col gap-3">
                    <Button
                        type="submit"
                        size="lg"
                        disabled={form.processing}
                        aria-busy={form.processing}
                        className="h-14 w-full rounded-xl text-base"
                    >
                        {form.processing && <Spinner />}
                        {form.processing ? 'Enviando…' : 'Enviar pedido'}
                    </Button>
                    <p className="text-center text-sm text-muted-foreground">
                        O pedido ainda não é uma reserva. Seus dados são usados
                        só para a secretaria falar com você sobre este pedido.
                    </p>
                </div>
            </form>
        </>
    );
}

Create.layout = { titulo: 'Solicitar uma sala' };
