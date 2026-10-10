import { Link } from '@inertiajs/react';
import { FileText, Settings } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { campos, gerar } from '@/routes/meus-certificados';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    itens: ModeloResumo[];
    buscando: boolean;
};

export default function SecaoCompartilhados({ itens, buscando }: Props) {
    return (
        <section
            aria-labelledby="compartilhados"
            className="flex flex-col gap-4"
        >
            <h2
                id="compartilhados"
                className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
            >
                Compartilhados comigo · {itens.length}
            </h2>

            {itens.length === 0 ? (
                <p className="rounded-2xl border border-dashed border-input px-5 py-8 text-center text-muted-foreground">
                    {buscando
                        ? 'Nenhum modelo compartilhado corresponde à busca.'
                        : 'Nenhum modelo foi compartilhado com você.'}
                </p>
            ) : (
                <ul className="divide-y rounded-2xl border bg-card">
                    {itens.map((modelo) => (
                        <li
                            key={modelo.id}
                            className="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-3.5"
                        >
                            <span className="flex h-10 w-14 shrink-0 items-center justify-center overflow-hidden rounded-sm bg-mesa">
                                <img
                                    src={modelo.imagem_url}
                                    alt=""
                                    loading="lazy"
                                    className="size-full object-contain"
                                />
                            </span>

                            <span className="flex min-w-0 flex-1 basis-[200px] flex-col leading-snug">
                                <span className="truncate font-semibold">
                                    {modelo.title}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    de {modelo.dono_nome} ·{' '}
                                    {modelo.campos_count}{' '}
                                    {modelo.campos_count === 1
                                        ? 'campo'
                                        : 'campos'}
                                </span>
                            </span>

                            {modelo.pode_editar ? (
                                <Badge className="rounded-full bg-sucesso-fundo text-sucesso hover:bg-sucesso-fundo">
                                    Pode editar
                                </Badge>
                            ) : (
                                <Badge variant="secondary">Só gerar</Badge>
                            )}

                            <div className="flex gap-2">
                                {modelo.campos_count === 0 ? (
                                    <Button
                                        variant="outline"
                                        className="min-h-11"
                                        disabled
                                        title="Este modelo ainda não tem campos"
                                    >
                                        <FileText />
                                        Gerar
                                    </Button>
                                ) : (
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="min-h-11"
                                    >
                                        <Link
                                            href={gerar({ modelo: modelo.id })}
                                        >
                                            <FileText />
                                            Gerar
                                        </Link>
                                    </Button>
                                )}
                                {modelo.pode_editar && (
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="min-h-11"
                                    >
                                        <Link
                                            href={campos({ modelo: modelo.id })}
                                        >
                                            <Settings />
                                            Campos
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
