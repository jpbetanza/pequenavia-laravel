import { Link } from '@inertiajs/react';
import { ChevronDown, type LucideIcon } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { SecaoDoMenu } from '@/lib/menu/secoes';

export const classeDaAba = (ativa: boolean) =>
    cn(
        'inline-flex h-16 items-center gap-2 rounded-sm border-b-2 px-3.5 text-[15px] font-semibold outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
        ativa
            ? 'border-primary text-primary'
            : 'border-transparent text-foreground/80 hover:text-foreground',
    );

type Props = {
    secao: SecaoDoMenu;
    ativa: boolean;
    icone: LucideIcon;
    /** Ação no fim do menu, separada por uma linha (ex.: "+ Nova reserva"). */
    acaoFinal?: { titulo: string; href: SecaoDoMenu['itens'][number]['href'] };
};

export function MenuDoModulo({ secao, ativa, icone: Icone, acaoFinal }: Props) {
    const { isCurrentUrl } = useCurrentUrl();
    const pendentes = secao.itens.reduce(
        (total, item) => total + (item.contador ?? 0),
        0,
    );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className={cn(classeDaAba(ativa), 'group')}
                >
                    <Icone aria-hidden="true" className="size-[18px]" />
                    {secao.titulo}
                    {pendentes > 0 && (
                        <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground">
                            <span className="sr-only">
                                {pendentes} pedidos pendentes
                            </span>
                            <span aria-hidden="true">{pendentes}</span>
                        </span>
                    )}
                    <ChevronDown
                        aria-hidden="true"
                        className="size-3.5 transition-transform group-data-[state=open]:rotate-180"
                    />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                sideOffset={6}
                className="w-[330px] rounded-2xl p-2"
            >
                {secao.itens.map((item) => {
                    const atual = isCurrentUrl(item.href);
                    const contador = item.contador ?? 0;

                    return (
                        <DropdownMenuItem
                            key={item.titulo}
                            asChild
                            className={cn(
                                'flex-col items-start gap-0.5 rounded-[10px] px-3 py-2.5',
                                atual && 'bg-secondary',
                            )}
                        >
                            <Link
                                href={item.href}
                                prefetch
                                aria-current={atual ? 'page' : undefined}
                            >
                                <span className="flex items-center gap-2 font-semibold">
                                    {item.titulo}
                                    {contador > 0 && (
                                        <span className="rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-foreground">
                                            <span className="sr-only">
                                                {contador} pedidos pendentes
                                            </span>
                                            <span aria-hidden="true">
                                                {contador}
                                            </span>
                                        </span>
                                    )}
                                </span>
                                <span className="text-[13px] text-muted-foreground">
                                    {item.descricao}
                                </span>
                            </Link>
                        </DropdownMenuItem>
                    );
                })}
                {acaoFinal && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            asChild
                            className="min-h-11 font-semibold text-primary"
                        >
                            <Link href={acaoFinal.href} prefetch>
                                + {acaoFinal.titulo}
                            </Link>
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
