import { Link } from '@inertiajs/react';
import { intervaloPorExtenso } from '@/lib/agenda/datas';
import { index as historico } from '@/routes/historico';
import type { Cartao } from '@/types/assistente';

type Dados = Extract<Cartao, { tipo: 'certificados' }>['dados'];

export function CartaoDeCertificados({ dados }: { dados: Dados }) {
    return (
        <section
            aria-label="Certificados emitidos"
            className="flex flex-col gap-3 rounded-xl border bg-background p-3 text-sm"
        >
            <div className="flex flex-col">
                <p className="text-xs text-muted-foreground">
                    {intervaloPorExtenso(dados.inicio, dados.fim)}
                </p>
                <p className="flex items-baseline gap-2">
                    <span className="font-serif text-3xl font-semibold tabular-nums">
                        {dados.total}
                    </span>
                    <span>
                        {dados.total === 1
                            ? 'certificado emitido'
                            : 'certificados emitidos'}
                    </span>
                </p>
            </div>

            {dados.por_modelo.length > 0 && (
                <ul className="flex flex-col divide-y border-t">
                    {dados.por_modelo.map((item) => (
                        <li
                            key={item.modelo}
                            className="flex justify-between gap-3 py-2"
                        >
                            <span className="min-w-0 truncate">
                                {item.modelo}
                            </span>
                            <span className="tabular-nums">{item.total}</span>
                        </li>
                    ))}
                </ul>
            )}

            <Link
                href={historico.url()}
                className="inline-flex min-h-11 items-center font-semibold text-primary underline-offset-4 hover:underline"
            >
                Ver histórico
            </Link>
        </section>
    );
}
