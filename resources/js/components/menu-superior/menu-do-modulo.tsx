import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuTrigger,
} from '@/components/ui/navigation-menu';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { SecaoDoMenu } from '@/lib/menu/secoes';

/**
 * Classes da aba do topo, aplicadas ao próprio NavigationMenuLink ou ao gatilho
 * (ambos passam por cn/tailwind-merge). Sem cantos, sem fundo e sem contorno no clique;
 * só o teclado ganha um fundo suave (focus-visible:bg-secondary).
 */
export const classeDaAba = (ativa: boolean) =>
    cn(
        'inline-flex h-16 flex-row items-center gap-2 rounded-none border-b-2 bg-transparent p-0 px-3.5 text-[15px] font-semibold outline-none hover:bg-transparent focus:bg-transparent focus-visible:bg-secondary focus-visible:ring-0 focus-visible:outline-0 focus-visible:outline-none data-[active=true]:bg-transparent data-[state=open]:bg-transparent data-[state=open]:focus-visible:bg-secondary',
        ativa
            ? 'border-primary text-primary focus:text-primary'
            : 'border-transparent text-foreground/80 hover:text-foreground focus:text-foreground/80',
    );

type Props = {
    secao: SecaoDoMenu;
    ativa: boolean;
    icone: LucideIcon;
    /** Ação no fim do menu, separada por uma linha (ex.: "+ Nova reserva"). */
    acaoFinal?: { titulo: string; href: SecaoDoMenu['itens'][number]['href'] };
};

/** Aba de um módulo: abre o conteúdo ao passar o mouse, por clique ou pelo teclado. */
export function MenuDoModulo({ secao, ativa, icone: Icone, acaoFinal }: Props) {
    const { isCurrentUrl } = useCurrentUrl();
    const pendentes = secao.itens.reduce(
        (total, item) => total + (item.contador ?? 0),
        0,
    );

    return (
        <NavigationMenuItem>
            <NavigationMenuTrigger
                aria-current={ativa ? 'page' : undefined}
                className={cn(
                    classeDaAba(ativa),
                    // Seta do próprio gatilho com 14 px (o componente shadcn fixa size-3).
                    '[&>svg:last-child]:size-3.5',
                )}
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
            </NavigationMenuTrigger>
            <NavigationMenuContent className="w-[330px] rounded-2xl p-2 group-data-[viewport=false]/navigation-menu:rounded-2xl md:w-[330px]">
                <ul className="grid gap-0.5">
                    {secao.itens.map((item) => {
                        const atual = isCurrentUrl(item.href);
                        const contador = item.contador ?? 0;

                        return (
                            <li key={item.titulo}>
                                <NavigationMenuLink
                                    asChild
                                    className={cn(
                                        'flex-col items-start gap-0.5 rounded-[10px] px-3 py-2.5',
                                        atual && 'bg-secondary',
                                    )}
                                >
                                    <Link
                                        href={item.href}
                                        prefetch
                                        aria-current={
                                            atual ? 'page' : undefined
                                        }
                                    >
                                        <span className="flex items-center gap-2 font-semibold">
                                            {item.titulo}
                                            {contador > 0 && (
                                                <span className="rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-foreground">
                                                    <span className="sr-only">
                                                        {contador} pedidos
                                                        pendentes
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
                                </NavigationMenuLink>
                            </li>
                        );
                    })}
                </ul>
                {acaoFinal && (
                    <>
                        <Separator className="my-1" />
                        <NavigationMenuLink
                            asChild
                            className="min-h-11 flex-row items-center font-semibold text-primary"
                        >
                            <Link href={acaoFinal.href} prefetch>
                                + {acaoFinal.titulo}
                            </Link>
                        </NavigationMenuLink>
                    </>
                )}
            </NavigationMenuContent>
        </NavigationMenuItem>
    );
}
