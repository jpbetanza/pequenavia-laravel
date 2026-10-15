import { Link, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    FileText,
    LayoutDashboard,
    LogOut,
    Settings,
    Sparkles,
    X,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useAssistente } from '@/hooks/use-assistente';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import type { IdDaAba, SecaoDoMenu } from '@/lib/menu/secoes';
import { cn } from '@/lib/utils';
import { inicio, logout } from '@/routes';
import { index as gestao } from '@/routes/gestao';
import { edit } from '@/routes/profile';

type Props = {
    aberto: boolean;
    onAbertoChange: (aberto: boolean) => void;
    secoes: SecaoDoMenu[];
    ativa: IdDaAba | null;
    assistenteDisponivel: boolean;
};

const ICONES_DAS_SECOES = {
    agendamentos: CalendarDays,
    certificados: FileText,
} as const;

/** Menu em tela cheia da barra superior, abaixo de md. Quem abre e fecha é a barra. */
export function MenuDoCelular({
    aberto,
    onAbertoChange,
    secoes,
    ativa,
    assistenteDisponivel,
}: Props) {
    const { auth } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();
    const getInitials = useInitials();
    const { alternar } = useAssistente();
    const cleanup = useMobileNavigation();

    const fechar = () => onAbertoChange(false);

    const navegou = () => {
        cleanup();
        fechar();
    };

    // O painel do assistente fica embaixo do menu: fecha o menu para ele aparecer.
    const abrirAssistente = () => {
        alternar();
        fechar();
    };

    const sair = () => {
        cleanup();
        fechar();
        // Mesmo comportamento do UserMenuContent: limpa as requisições pendentes antes do logout.
        router.flushAll();
    };

    return (
        <Sheet open={aberto} onOpenChange={onAbertoChange}>
            <SheetContent
                id="menu-do-celular"
                side="top"
                className="h-svh gap-0 overflow-y-auto p-0 [&>button]:hidden"
            >
                <SheetTitle className="sr-only">Menu</SheetTitle>
                <SheetDescription className="sr-only">
                    Navegação do app
                </SheetDescription>

                <div className="flex h-16 shrink-0 items-center gap-2.5 border-b border-border px-4">
                    <Link
                        href={inicio()}
                        prefetch
                        onClick={navegou}
                        className="flex items-center gap-2.5"
                    >
                        <AppLogo />
                    </Link>

                    <div className="ml-auto flex items-center gap-3">
                        {assistenteDisponivel && (
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Abrir o assistente"
                                aria-controls="painel-assistente"
                                onClick={abrirAssistente}
                                className="size-11 rounded-full"
                            >
                                <Sparkles
                                    aria-hidden="true"
                                    className="size-4"
                                />
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="default"
                            size="icon"
                            aria-label="Fechar menu"
                            aria-controls="menu-do-celular"
                            onClick={fechar}
                            className="size-11 rounded-xl"
                        >
                            <X aria-hidden="true" className="size-[22px]" />
                        </Button>
                    </div>
                </div>

                <nav
                    aria-label="Navegação do app"
                    className="flex flex-col gap-1.5 px-3 pt-3 pb-6"
                >
                    <Link
                        href={inicio()}
                        prefetch
                        onClick={navegou}
                        aria-current={ativa === 'inicio' ? 'page' : undefined}
                        className={itemDoCelular(
                            ativa === 'inicio',
                            'text-[17px]',
                        )}
                    >
                        Início
                    </Link>

                    {secoes.map((secao) => {
                        const Icone = ICONES_DAS_SECOES[secao.id];

                        return (
                            <div key={secao.id} className="flex flex-col">
                                <p className="mt-3.5 mb-1 flex items-center gap-2.5 px-3.5 text-xs font-semibold tracking-[0.12em] text-dourado uppercase">
                                    <Icone
                                        aria-hidden="true"
                                        className="size-[18px]"
                                    />
                                    {secao.titulo}
                                </p>
                                {secao.itens.map((item) => {
                                    const atual = isCurrentUrl(item.href);
                                    const contador = item.contador ?? 0;

                                    return (
                                        <Link
                                            key={item.titulo}
                                            href={item.href}
                                            prefetch
                                            onClick={navegou}
                                            aria-current={
                                                atual ? 'page' : undefined
                                            }
                                            className={itemDoCelular(
                                                atual,
                                                'justify-between text-base font-medium',
                                            )}
                                        >
                                            {item.titulo}
                                            {contador > 0 && (
                                                <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground">
                                                    <span className="sr-only">
                                                        {contador} pedidos
                                                        pendentes
                                                    </span>
                                                    <span aria-hidden="true">
                                                        {contador}
                                                    </span>
                                                </span>
                                            )}
                                        </Link>
                                    );
                                })}
                            </div>
                        );
                    })}
                </nav>

                {auth.user && (
                    <div className="mt-auto flex items-center gap-3 border-t border-border px-4 py-4">
                        <span
                            aria-hidden="true"
                            className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                        >
                            {getInitials(auth.user.name)}
                        </span>
                        <div className="flex min-w-0 flex-1 flex-col">
                            <span className="truncate font-semibold">
                                {auth.user.name}
                            </span>
                            <div className="flex flex-wrap items-center text-sm text-muted-foreground">
                                {auth.user.admin && (
                                    <>
                                        <Link
                                            href={gestao()}
                                            prefetch
                                            onClick={navegou}
                                            className="inline-flex min-h-11 items-center gap-1.5 rounded-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <LayoutDashboard
                                                aria-hidden="true"
                                                className="size-4"
                                            />
                                            Gestão
                                        </Link>
                                        <span
                                            aria-hidden="true"
                                            className="px-1.5"
                                        >
                                            ·
                                        </span>
                                    </>
                                )}
                                <Link
                                    href={edit()}
                                    prefetch
                                    onClick={navegou}
                                    className="inline-flex min-h-11 items-center gap-1.5 rounded-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <Settings
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                    Configurações
                                </Link>
                                <span aria-hidden="true" className="px-1.5">
                                    ·
                                </span>
                                <Link
                                    href={logout()}
                                    as="button"
                                    onClick={sair}
                                    data-test="logout-button"
                                    className="inline-flex min-h-11 items-center gap-1.5 rounded-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <LogOut
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                    Sair
                                </Link>
                            </div>
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}

function itemDoCelular(ativo: boolean, extra?: string) {
    return cn(
        'flex min-h-12 items-center rounded-[10px] px-3.5 text-[15px] font-medium outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
        ativo
            ? 'bg-card text-primary shadow-sm'
            : 'text-foreground hover:bg-secondary',
        extra,
    );
}
