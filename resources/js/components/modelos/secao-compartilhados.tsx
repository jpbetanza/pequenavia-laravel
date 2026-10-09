import { Link } from '@inertiajs/react';
import { FileText, Settings } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { campos, gerar } from '@/routes/meus-certificados';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    itens: ModeloResumo[];
};

export default function SecaoCompartilhados({ itens }: Props) {
    if (itens.length === 0) {
        return null;
    }

    return (
        <section className="mt-12">
            <div className="mb-6">
                <h2 className="font-serif text-2xl font-bold">
                    Compartilhados comigo
                </h2>
                <p className="mt-1 text-muted-foreground">
                    Modelos que outros usuários compartilharam com você.
                </p>
            </div>

            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                {itens.map((modelo) => (
                    <Card
                        key={modelo.id}
                        className="gap-0 overflow-hidden py-0"
                    >
                        <div className="aspect-[4/3] w-full overflow-hidden bg-muted">
                            <img
                                src={modelo.imagem_url}
                                alt={modelo.title}
                                loading="lazy"
                                className="size-full object-cover"
                            />
                        </div>
                        <div className="flex flex-col gap-2 p-4">
                            <div>
                                <p className="line-clamp-1 text-sm font-semibold">
                                    {modelo.title}
                                </p>
                                {modelo.description && (
                                    <p className="line-clamp-2 text-xs text-muted-foreground">
                                        {modelo.description}
                                    </p>
                                )}
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Compartilhado por {modelo.dono_nome}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-1">
                                <Badge variant="secondary">Pode gerar</Badge>
                                {modelo.pode_editar && (
                                    <Badge>Pode editar</Badge>
                                )}
                            </div>
                            <div className="flex gap-2">
                                {modelo.campos_count === 0 ? (
                                    <Button
                                        size="sm"
                                        className="flex-1"
                                        disabled
                                        title="Este modelo ainda não tem campos"
                                    >
                                        <FileText />
                                        Gerar
                                    </Button>
                                ) : (
                                    <Button
                                        asChild
                                        size="sm"
                                        className="flex-1"
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
                                        size="sm"
                                        variant="secondary"
                                        className="flex-1"
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
                        </div>
                    </Card>
                ))}
            </div>
        </section>
    );
}
