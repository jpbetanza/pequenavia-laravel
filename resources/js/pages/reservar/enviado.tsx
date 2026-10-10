import { Head, Link } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { create as rotaDoFormulario } from '@/routes/reservar';
import type { AgendaPublica } from '@/types/agendamentos';

export default function Enviado({ agenda }: { agenda: AgendaPublica }) {
    return (
        <>
            <Head title="Pedido enviado" />

            <section
                aria-live="polite"
                className="flex flex-col gap-6 rounded-2xl border bg-card p-6 sm:p-8"
            >
                <p className="text-lg leading-relaxed">
                    Pedido enviado. A secretaria vai entrar em contato pelo
                    WhatsApp.
                </p>
                <Link
                    href={rotaDoFormulario(agenda.slug)}
                    className={buttonVariants({
                        variant: 'outline',
                        size: 'lg',
                        className: 'h-12 w-full rounded-xl text-base sm:w-fit',
                    })}
                >
                    Fazer outro pedido
                </Link>
            </section>
        </>
    );
}

Enviado.layout = { titulo: 'Pedido enviado' };
