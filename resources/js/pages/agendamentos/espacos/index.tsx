import { Head, Link } from '@inertiajs/react';
import { Building2, Info, Pencil, Plus } from 'lucide-react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import { Button } from '@/components/ui/button';
import { corDoEspaco } from '@/lib/agenda/cores';
import { resumirHorarios } from '@/lib/agenda/horarios';
import { cn } from '@/lib/utils';
import { create, edit, index as rota } from '@/routes/agendamentos/espacos';
import type { EspacoResumo, SituacaoDoEspaco } from '@/types/agendamentos';

type Props = {
    espacos: EspacoResumo[];
};

const SELOS: Record<SituacaoDoEspaco, { rotulo: string; classes: string }> = {
    ativo: { rotulo: 'Ativo', classes: 'bg-sucesso-fundo text-sucesso' },
    bloqueado: { rotulo: 'Bloqueado', classes: 'bg-aviso-fundo text-aviso' },
    inativo: { rotulo: 'Inativo', classes: 'bg-muted text-muted-foreground' },
};

function descreverEspaco(espaco: EspacoResumo): string {
    const pessoas =
        espaco.capacidade === null
            ? null
            : `Até ${espaco.capacidade} ${espaco.capacidade === 1 ? 'pessoa' : 'pessoas'}`;

    return [pessoas, espaco.localizacao].filter(Boolean).join(' · ');
}

function contarReservas(quantidade: number): string {
    return `${quantidade} ${quantidade === 1 ? 'reserva' : 'reservas'} nesta semana`;
}

export default function Espacos({ espacos }: Props) {
    return (
        <>
            <Head title="Espaços" />
            <div className="flex flex-col gap-10 p-4">
                <CabecalhoDaPagina
                    titulo="Espaços"
                    subtitulo="Salas e ambientes que podem ser reservados, com capacidade e horários de uso."
                    acoes={
                        <Button asChild className="h-11 rounded-full px-5">
                            <Link href={create.url()}>
                                <Plus />
                                Novo espaço
                            </Link>
                        </Button>
                    }
                />

                {espacos.length === 0 ? (
                    <section
                        aria-label="Nenhum espaço cadastrado"
                        className="flex flex-col items-center gap-3 rounded-2xl border border-dashed bg-card p-10 text-center text-muted-foreground"
                    >
                        <p>
                            Você ainda não tem espaços. Cadastre o primeiro para
                            começar.
                        </p>
                        <Button asChild className="min-h-11">
                            <Link href={create.url()}>
                                <Plus />
                                Novo espaço
                            </Link>
                        </Button>
                    </section>
                ) : (
                    <ul className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        {espacos.map((espaco) => (
                            <li key={espaco.id}>
                                <CartaoDeEspaco espaco={espaco} />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

function CartaoDeEspaco({ espaco }: { espaco: EspacoResumo }) {
    const paleta = corDoEspaco(espaco.cor);
    const selo = SELOS[espaco.situacao];
    const descricao = descreverEspaco(espaco);

    return (
        <article
            className={cn(
                'flex h-full flex-col gap-4 rounded-2xl border bg-card p-5',
                espaco.situacao !== 'ativo' && 'border-dashed',
            )}
        >
            <div className="flex items-start gap-3">
                <span
                    className="flex size-10 shrink-0 items-center justify-center rounded-xl"
                    style={{
                        backgroundColor: paleta.fundo,
                        color: paleta.forte,
                    }}
                >
                    <Building2 className="size-5" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="text-lg leading-tight font-semibold">
                        {espaco.nome}
                    </h2>
                    {descricao !== '' && (
                        <p className="text-sm text-muted-foreground">
                            {descricao}
                        </p>
                    )}
                </div>
                <span
                    className={cn(
                        'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                        selo.classes,
                    )}
                >
                    {selo.rotulo}
                </span>
            </div>

            {espaco.bloqueio_atual && (
                <p className="flex gap-2 text-sm text-aviso">
                    <Info
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    Em {espaco.bloqueio_atual.motivo} até{' '}
                    {espaco.bloqueio_atual.ate}
                </p>
            )}

            {espaco.recursos_fixos.length > 0 && (
                <ul className="flex flex-wrap gap-1.5">
                    {espaco.recursos_fixos.map((recurso) => (
                        <li
                            key={recurso}
                            className="rounded-full bg-muted px-2.5 py-1 text-xs"
                        >
                            {recurso}
                        </li>
                    ))}
                </ul>
            )}

            <p className="text-sm">{resumirHorarios(espaco.horarios)}</p>

            <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                <span className="text-sm text-muted-foreground">
                    {contarReservas(espaco.reservas_na_semana)}
                </span>
                <Button variant="outline" className="min-h-11" asChild>
                    <Link
                        href={edit.url({ espaco: espaco.id })}
                        aria-label={`Editar ${espaco.nome}`}
                    >
                        <Pencil />
                        Editar
                    </Link>
                </Button>
            </div>
        </article>
    );
}

Espacos.layout = {
    breadcrumbs: [{ title: 'Espaços', href: rota() }],
};
