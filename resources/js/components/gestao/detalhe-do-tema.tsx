import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { formatarData } from '@/lib/gestao/formatar';
import { cn } from '@/lib/utils';
import { juntar, update } from '@/routes/gestao/temas';
import type {
    SituacaoDoTema,
    TemaLinha,
    TemaSelecionado,
} from '@/types/gestao';

type Props = {
    selecionado: TemaSelecionado;
    temas: TemaLinha[];
};

const ESTILO_DA_SITUACAO: Record<SituacaoDoTema, string> = {
    novo: 'bg-[#D9B97A] text-[#221A17]',
    acompanhando: 'bg-[#F3E8CF] text-[#5A4214]',
    planejado: 'bg-[#E2EEE8] text-[#1B3D30]',
    descartado: 'bg-secondary text-muted-foreground',
};

const ACOES_DE_SITUACAO: { situacao: SituacaoDoTema; rotulo: string }[] = [
    { situacao: 'acompanhando', rotulo: 'Marcar como acompanhando' },
    { situacao: 'planejado', rotulo: 'Marcar como planejado' },
    { situacao: 'descartado', rotulo: 'Descartar' },
];

function origemDoTema(tema: TemaLinha): string {
    const data = formatarData(tema.criado_em);

    return tema.criado_por === 'haiku'
        ? `Criado pelo Haiku em ${data}`
        : `Criado pelo gestor em ${data}`;
}

/** Tema escolhido na lista: descrição, exemplos do Haiku e as ações de gestão sobre o tema. */
export function DetalheDoTema({ selecionado, temas }: Props) {
    const { tema, exemplos } = selecionado;
    const [dialogo, setDialogo] = useState<'renomear' | 'juntar' | null>(null);
    const [ocupado, setOcupado] = useState(false);
    const outrosTemas = temas.filter((outro) => outro.id !== tema.id);

    function mudarSituacao(situacao: SituacaoDoTema) {
        router.patch(
            update.url({ tema: tema.id }),
            { situacao },
            {
                preserveScroll: true,
                onStart: () => setOcupado(true),
                onFinish: () => setOcupado(false),
            },
        );
    }

    return (
        <section
            aria-label="Tema escolhido"
            className="flex min-w-0 flex-col overflow-hidden rounded-[18px] border border-border bg-card"
        >
            <div className="flex flex-col gap-2 border-b px-5 py-4">
                <div className="flex flex-wrap items-center gap-3">
                    <h2 className="min-w-0 flex-1 font-serif text-2xl leading-tight font-semibold break-words">
                        {tema.nome}
                    </h2>
                    <span
                        className={cn(
                            'rounded-full px-2.5 py-0.5 text-sm font-semibold',
                            ESTILO_DA_SITUACAO[tema.situacao],
                        )}
                    >
                        {tema.situacao_rotulo}
                    </span>
                </div>
                <p className="text-sm text-[#4A3D38]">
                    {tema.descricao ?? 'Sem descrição.'}
                </p>
                <span className="text-xs text-muted-foreground">
                    {origemDoTema(tema)}
                </span>
            </div>

            <div className="flex flex-1 flex-col gap-2.5 bg-[#FBF8F2] px-5 py-4">
                <h3 className="text-xs font-semibold tracking-[0.08em] text-[#8A6420] uppercase">
                    O que as secretarias disseram · resumos do Haiku
                </h3>
                {exemplos.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nenhum exemplo neste período.
                    </p>
                ) : (
                    exemplos.map((exemplo, indice) => (
                        <blockquote
                            key={`${exemplo.paroquia}-${exemplo.data}-${indice}`}
                            className="rounded-r-[10px] border-l-[3px] border-[#D9B97A] bg-card px-3 py-2.5"
                        >
                            <p>{exemplo.resumo}</p>
                            <footer className="mt-1 text-xs text-muted-foreground">
                                {exemplo.paroquia}, {formatarData(exemplo.data)}
                            </footer>
                        </blockquote>
                    ))
                )}
            </div>

            <div className="flex flex-wrap gap-2 border-t px-5 py-3">
                {ACOES_DE_SITUACAO.filter(
                    (acao) => acao.situacao !== tema.situacao,
                ).map((acao) => (
                    <Button
                        key={acao.situacao}
                        type="button"
                        variant="outline"
                        disabled={ocupado}
                        onClick={() => mudarSituacao(acao.situacao)}
                        className={cn(
                            'min-h-11 sm:min-h-9',
                            acao.situacao === 'descartado' &&
                                'text-[#A33A2A] hover:text-[#A33A2A]',
                        )}
                    >
                        {acao.rotulo}
                    </Button>
                ))}
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setDialogo('juntar')}
                    disabled={outrosTemas.length === 0}
                    className="min-h-11 sm:min-h-9"
                >
                    Juntar com outro tema
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setDialogo('renomear')}
                    className="min-h-11 sm:min-h-9"
                >
                    Renomear
                </Button>
            </div>

            <DialogoRenomear
                tema={tema}
                aberto={dialogo === 'renomear'}
                aoFechar={() => setDialogo(null)}
            />
            <DialogoJuntar
                tema={tema}
                outros={outrosTemas}
                aberto={dialogo === 'juntar'}
                aoFechar={() => setDialogo(null)}
            />
        </section>
    );
}

type DialogoProps = {
    tema: TemaLinha;
    aberto: boolean;
    aoFechar: () => void;
};

function FormularioRenomear({ tema, aoFechar }: Omit<DialogoProps, 'aberto'>) {
    const formulario = useForm({
        nome: tema.nome,
        descricao: tema.descricao ?? '',
    });

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();
        formulario.transform((dados) => ({
            nome: dados.nome.trim(),
            descricao:
                dados.descricao.trim() === '' ? null : dados.descricao.trim(),
        }));
        formulario.patch(update.url({ tema: tema.id }), {
            preserveScroll: true,
            onSuccess: aoFechar,
        });
    };

    return (
        <form onSubmit={enviar} className="grid gap-4">
            <DialogHeader>
                <DialogTitle>Renomear tema</DialogTitle>
                <DialogDescription>
                    O nome e a descrição aparecem para o JEV na próxima rodada.
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-2">
                <Label htmlFor="renomear-nome">Nome</Label>
                <Input
                    id="renomear-nome"
                    value={formulario.data.nome}
                    maxLength={80}
                    required
                    onChange={(evento) =>
                        formulario.setData('nome', evento.target.value)
                    }
                    className="min-h-11 sm:min-h-9"
                />
                <InputError message={formulario.errors.nome} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="renomear-descricao">Descrição</Label>
                <Textarea
                    id="renomear-descricao"
                    value={formulario.data.descricao}
                    maxLength={300}
                    onChange={(evento) =>
                        formulario.setData('descricao', evento.target.value)
                    }
                />
                <InputError message={formulario.errors.descricao} />
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={aoFechar}
                    disabled={formulario.processing}
                    className="min-h-11 sm:min-h-9"
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    disabled={formulario.processing}
                    className="min-h-11 sm:min-h-9"
                >
                    {formulario.processing && <Spinner />}
                    Salvar
                </Button>
            </DialogFooter>
        </form>
    );
}

function DialogoRenomear({ tema, aberto, aoFechar }: DialogoProps) {
    return (
        <Dialog open={aberto} onOpenChange={(abrir) => !abrir && aoFechar()}>
            <DialogContent>
                {aberto && (
                    <FormularioRenomear
                        key={tema.id}
                        tema={tema}
                        aoFechar={aoFechar}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function FormularioJuntar({
    tema,
    outros,
    aoFechar,
}: Omit<DialogoProps, 'aberto'> & { outros: TemaLinha[] }) {
    const formulario = useForm({ destino_id: '' });

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();
        formulario.post(juntar.url({ tema: tema.id }), {
            preserveScroll: true,
            onSuccess: aoFechar,
        });
    };

    return (
        <form onSubmit={enviar} className="grid gap-4">
            <DialogHeader>
                <DialogTitle>Juntar {tema.nome} com outro tema</DialogTitle>
                <DialogDescription>
                    As conversas deste tema passam para o tema escolhido, e{' '}
                    {tema.nome} deixa a lista.
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-2">
                <Label htmlFor="juntar-destino">Tema de destino</Label>
                <Select
                    value={formulario.data.destino_id}
                    onValueChange={(valor) =>
                        formulario.setData('destino_id', valor)
                    }
                >
                    <SelectTrigger
                        id="juntar-destino"
                        className="min-h-11 w-full sm:min-h-9"
                    >
                        <SelectValue placeholder="Escolha o tema" />
                    </SelectTrigger>
                    <SelectContent>
                        {outros.map((outro) => (
                            <SelectItem key={outro.id} value={outro.id}>
                                {outro.nome}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={formulario.errors.destino_id} />
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={aoFechar}
                    disabled={formulario.processing}
                    className="min-h-11 sm:min-h-9"
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    disabled={
                        formulario.data.destino_id === '' ||
                        formulario.processing
                    }
                    className="min-h-11 sm:min-h-9"
                >
                    {formulario.processing && <Spinner />}
                    Juntar temas
                </Button>
            </DialogFooter>
        </form>
    );
}

function DialogoJuntar({
    tema,
    outros,
    aberto,
    aoFechar,
}: DialogoProps & { outros: TemaLinha[] }) {
    return (
        <Dialog open={aberto} onOpenChange={(abrir) => !abrir && aoFechar()}>
            <DialogContent>
                {aberto && (
                    <FormularioJuntar
                        key={tema.id}
                        tema={tema}
                        outros={outros}
                        aoFechar={aoFechar}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
