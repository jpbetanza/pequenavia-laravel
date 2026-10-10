import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { AgendaPublica } from '@/types/agendamentos';

type Props = {
    /** Título da página, enviado pelo `Pagina.layout` de cada página pública. */
    titulo?: string;
    children: ReactNode;
};

/**
 * Layout das páginas públicas de reserva (`reservar/*`): sem menu nem login, só o cabeçalho da paróquia
 * e uma coluna de leitura que se adapta ao celular.
 */
export default function LayoutPublico({ titulo, children }: Props) {
    const { agenda } = usePage<{ agenda: AgendaPublica }>().props;

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <header className="bg-primary text-primary-foreground">
                <div className="mx-auto flex w-full max-w-2xl flex-col gap-4 px-5 py-6 sm:px-8 sm:py-8">
                    <div className="flex items-center gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-card">
                            <AppLogoIcon
                                aria-hidden="true"
                                alt=""
                                className="size-7 object-contain"
                            />
                        </span>
                        <span className="min-w-0 text-sm text-primary-foreground/85">
                            Paróquia {agenda.nome}
                        </span>
                    </div>
                    {titulo && (
                        <h1 className="font-serif text-4xl leading-none font-semibold sm:text-5xl">
                            {titulo}
                        </h1>
                    )}
                </div>
            </header>

            <main className="mx-auto w-full max-w-2xl flex-1 px-5 py-8 sm:px-8 sm:py-10">
                {children}
            </main>
        </div>
    );
}
