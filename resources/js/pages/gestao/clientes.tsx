import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { SeletorDePeriodo } from '@/components/gestao/seletor-de-periodo';
import { TabelaDeClientes } from '@/components/gestao/tabela-de-clientes';
import { Input } from '@/components/ui/input';
import { formatarNumero, hojeNoFuso } from '@/lib/gestao/formatar';
import { index } from '@/routes/gestao/clientes';
import type { ClientesProps } from '@/types/gestao';

const DEBOUNCE_DA_BUSCA_MS = 300;
const LIMITE_DA_BUSCA = 100;

export default function Clientes({ periodo, busca, clientes }: ClientesProps) {
    const [agora] = useState(() => new Date());
    const [termo, setTermo] = useState(busca ?? '');
    const buscaAplicada = busca ?? '';

    useEffect(() => {
        const digitado = termo.trim();

        if (digitado === buscaAplicada) {
            return;
        }

        const temporizador = window.setTimeout(() => {
            router.get(
                index(),
                {
                    periodo,
                    ...(digitado !== '' ? { busca: digitado } : {}),
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, DEBOUNCE_DA_BUSCA_MS);

        return () => window.clearTimeout(temporizador);
    }, [termo, buscaAplicada, periodo]);

    const quantidade = clientes.length;
    const contagem = `${formatarNumero(quantidade)} ${quantidade === 1 ? 'paróquia' : 'paróquias'}`;

    return (
        <>
            <Head title="Clientes" />

            <div className="flex flex-col gap-6">
                <header className="flex flex-wrap items-end gap-4">
                    <div className="min-w-64 flex-1">
                        <p className="text-sm text-muted-foreground">
                            {contagem}
                            {buscaAplicada !== '' && ` para «${buscaAplicada}»`}
                        </p>
                        <h1 className="mt-1 font-serif text-4xl leading-none font-semibold sm:text-5xl">
                            Clientes
                        </h1>
                    </div>
                    <SeletorDePeriodo periodo={periodo} />
                </header>

                <section
                    aria-label="Paróquias"
                    className="flex min-w-0 flex-col overflow-hidden rounded-[18px] border border-border bg-card"
                >
                    <div className="border-b border-border p-4">
                        <div className="relative w-full sm:w-80">
                            <Search
                                aria-hidden="true"
                                className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                type="search"
                                aria-label="Buscar paróquia por nome ou e-mail"
                                placeholder="Buscar por nome ou e-mail"
                                value={termo}
                                maxLength={LIMITE_DA_BUSCA}
                                onChange={(evento) =>
                                    setTermo(evento.target.value)
                                }
                                className="h-11 pl-10 sm:h-9"
                            />
                        </div>
                    </div>

                    <TabelaDeClientes
                        clientes={clientes}
                        hoje={hojeNoFuso(agora)}
                    />
                </section>
            </div>
        </>
    );
}
