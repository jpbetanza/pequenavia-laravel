import { Link } from '@inertiajs/react';
import type { Alerta, NivelDoAlerta } from '@/types/gestao';

type Props = {
    alertas: Alerta[];
};

const COR_DO_NIVEL: Record<NivelDoAlerta, string> = {
    alto: 'bg-[#A33A2A]',
    medio: 'bg-[#B8892F]',
    baixo: 'bg-[#8A7A70]',
};

const PRIORIDADE: Record<NivelDoAlerta, string> = {
    alto: 'alta',
    medio: 'média',
    baixo: 'baixa',
};

/** Painel dourado com o que pede atenção agora. A cor do ponto repete o nível em texto para leitores de tela. */
export function ListaDeAlertas({ alertas }: Props) {
    return (
        <section
            aria-labelledby="pedem-atencao"
            className="flex flex-col gap-3 rounded-[18px] border border-[#E8D7B0] bg-[#F3E8CF] p-5 text-[#3E2D0E]"
        >
            <h2
                id="pedem-atencao"
                className="font-serif text-2xl leading-none font-semibold"
            >
                Pedem atenção
            </h2>

            {alertas.length === 0 ? (
                <p className="text-sm">Nada pede atenção neste período.</p>
            ) : (
                <ul className="flex flex-col gap-2.5">
                    {alertas.map((alerta) => (
                        <li
                            key={`${alerta.tipo}-${alerta.titulo}`}
                            className="flex gap-2.5 text-sm leading-snug"
                        >
                            <span
                                aria-hidden="true"
                                className={`mt-1.5 size-2 shrink-0 rounded-full ${COR_DO_NIVEL[alerta.nivel]}`}
                            />
                            <span className="min-w-0">
                                <span className="sr-only">
                                    Prioridade {PRIORIDADE[alerta.nivel]}:{' '}
                                </span>
                                <b>{alerta.titulo}</b> {alerta.texto}
                                {alerta.link && (
                                    <>
                                        {' '}
                                        <Link
                                            href={alerta.link}
                                            className="font-semibold text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            Ver
                                            <span className="sr-only">
                                                {' '}
                                                : {alerta.titulo}
                                            </span>
                                        </Link>
                                    </>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
