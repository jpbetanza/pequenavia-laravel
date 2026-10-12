import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const beneficios = [
    'Salas e equipamentos sem choque de horário',
    'Pedidos das pastorais com conflito à vista',
    'Um assistente que conhece a sua agenda',
    'Certificados gerados a partir do Excel',
];

export default function AuthSimpleLayout({
    children,
    title,
    description,
    painel,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col bg-background md:flex-row">
            <aside className="flex flex-col gap-6 bg-primary px-6 py-6 text-primary-foreground md:w-[44%] md:min-w-[400px] md:justify-between md:gap-12 md:px-14 md:py-12">
                <Link
                    href={home()}
                    aria-label="PequenaVia, página inicial"
                    className="flex w-fit items-center gap-3"
                >
                    <span className="flex size-11 items-center justify-center rounded-full bg-card">
                        <AppLogoIcon className="size-8 object-contain" />
                    </span>
                    <span className="font-serif text-2xl font-bold">
                        PequenaVia
                    </span>
                </Link>

                <div className="flex max-w-md flex-col gap-5">
                    {/* Sem painel, o título da página já aparece no formulário: aqui só fica no desktop. */}
                    <p
                        className={cn(
                            'font-serif text-3xl leading-tight font-semibold md:text-5xl',
                            !painel && 'hidden md:block',
                        )}
                    >
                        {painel ?? title}
                    </p>
                    {painel && (
                        <p className="hidden text-lg text-primary-foreground/85 md:block">
                            A agenda das salas, os pedidos das pastorais e os
                            certificados estão onde você deixou.
                        </p>
                    )}
                </div>

                <ul className="hidden flex-col gap-3.5 text-[15px] text-primary-foreground/85 md:flex">
                    {beneficios.map((beneficio) => (
                        <li key={beneficio} className="flex items-center gap-3">
                            <Check className="size-4.5 shrink-0" aria-hidden />
                            {beneficio}
                        </li>
                    ))}
                </ul>
            </aside>

            <main className="flex flex-1 items-start justify-center px-6 py-10 md:items-center md:px-8 md:py-14">
                <div className="flex w-full max-w-[420px] flex-col gap-7">
                    <div className="flex flex-col gap-2">
                        <h1 className="font-serif text-4xl leading-none font-semibold md:text-5xl">
                            {title}
                        </h1>
                        {description && (
                            <p className="text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
