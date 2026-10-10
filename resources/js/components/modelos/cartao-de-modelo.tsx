import { Link } from '@inertiajs/react';
import {
    CircleAlert,
    Ellipsis,
    FileText,
    Pencil,
    Settings,
    Share2,
    Trash2,
    Users,
    ArrowRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { tempoRelativo } from '@/lib/tempo';
import { cn } from '@/lib/utils';
import { campos, gerar } from '@/routes/meus-certificados';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    modelo: ModeloResumo;
    aoAlterar: () => void;
    aoCompartilhar: () => void;
    aoExcluir: () => void;
};

export default function CartaoDeModelo({
    modelo,
    aoAlterar,
    aoCompartilhar,
    aoExcluir,
}: Props) {
    const semCampos = modelo.campos_count === 0;
    const alterado = tempoRelativo(modelo.atualizado_em);
    const pessoas = modelo.compartilhamentos.length;
    const dicaSemCampos = 'Posicione os campos antes de gerar certificados';

    const resumo = [
        `${modelo.campos_count} ${modelo.campos_count === 1 ? 'campo' : 'campos'}`,
        alterado && `alterado ${alterado}`,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <article
            className={cn(
                'flex flex-col overflow-hidden rounded-2xl border bg-card',
                semCampos
                    ? 'border-dashed border-input'
                    : 'border-border shadow-xs',
            )}
        >
            <div className="flex h-[196px] items-center justify-center bg-mesa px-10 py-[18px]">
                <img
                    src={modelo.imagem_url}
                    alt={`Arte do modelo ${modelo.title}`}
                    loading="lazy"
                    className="size-full object-contain"
                />
            </div>

            <div className="flex flex-1 flex-col gap-3.5 p-5">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h3 className="truncate text-[17px] font-semibold">
                            {modelo.title}
                        </h3>
                        {semCampos ? (
                            <p className="mt-0.5 flex items-center gap-1.5 text-sm font-medium text-aviso">
                                <CircleAlert
                                    className="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                Nenhum campo ainda
                            </p>
                        ) : (
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                {resumo}
                            </p>
                        )}
                    </div>

                    {pessoas > 0 && (
                        <span className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-secondary px-2.5 py-1 text-xs font-semibold text-secondary-foreground">
                            <Users className="size-3.5" aria-hidden="true" />
                            {pessoas} {pessoas === 1 ? 'pessoa' : 'pessoas'}
                        </span>
                    )}
                </div>

                <div className="flex gap-2">
                    {semCampos ? (
                        <>
                            <Button
                                variant="outline"
                                className="min-h-11 flex-1"
                                disabled
                                title={dicaSemCampos}
                            >
                                <FileText />
                                Gerar
                            </Button>
                            <Button
                                asChild
                                className="min-h-11 flex-1 bg-foreground text-background hover:bg-foreground/90"
                            >
                                <Link href={campos({ modelo: modelo.id })}>
                                    Posicionar os campos
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button asChild className="min-h-11 flex-1">
                                <Link href={gerar({ modelo: modelo.id })}>
                                    <FileText />
                                    Gerar
                                </Link>
                            </Button>
                            {modelo.pode_editar && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="min-h-11 flex-1"
                                >
                                    <Link href={campos({ modelo: modelo.id })}>
                                        <Settings />
                                        Campos
                                    </Link>
                                </Button>
                            )}
                        </>
                    )}

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-11 shrink-0"
                                aria-label={`Mais opções de ${modelo.title}`}
                            >
                                <Ellipsis />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {modelo.pode_editar && (
                                <DropdownMenuItem onSelect={aoAlterar}>
                                    <Pencil />
                                    Alterar
                                </DropdownMenuItem>
                            )}
                            {modelo.eh_dono && (
                                <DropdownMenuItem onSelect={aoCompartilhar}>
                                    <Share2 />
                                    Compartilhar
                                </DropdownMenuItem>
                            )}
                            {modelo.eh_dono && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        className="text-destructive focus:text-destructive"
                                        onSelect={aoExcluir}
                                    >
                                        <Trash2 />
                                        Excluir
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </article>
    );
}
