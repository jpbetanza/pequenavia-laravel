import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { linhaDeCertificados } from '@/lib/inicio/resumo';
import { index as meusCertificados } from '@/routes/meus-certificados';

type Props = {
    emitidos: number;
    dataHoje: string;
    ultimo: { modelo: string; gerado_em: string } | null;
};

/** Atalho para gerar um certificado, com o resumo do mês. */
export function AtalhoDeCertificados({ emitidos, dataHoje, ultimo }: Props) {
    return (
        <section
            aria-labelledby="certificados-do-inicio"
            className="flex flex-wrap items-center gap-4 rounded-[18px] border border-border p-5 sm:px-6"
        >
            <div className="flex min-w-[200px] flex-1 flex-col gap-0.5">
                <h2
                    id="certificados-do-inicio"
                    className="text-[15px] font-semibold"
                >
                    Certificados
                </h2>
                <span className="text-sm text-muted-foreground">
                    {linhaDeCertificados({ emitidos, dataHoje, ultimo })}
                </span>
            </div>
            <Button asChild variant="outline" className="min-h-11 px-4">
                <Link href={meusCertificados()} prefetch>
                    Gerar certificado
                </Link>
            </Button>
        </section>
    );
}
