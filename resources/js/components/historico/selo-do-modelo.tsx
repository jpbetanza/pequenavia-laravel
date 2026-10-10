import { corDoSelo } from '@/lib/historico';
import { cn } from '@/lib/utils';

type Props = {
    titulo: string;
};

export default function SeloDoModelo({ titulo }: Props) {
    return (
        <span
            className={cn(
                'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                corDoSelo(titulo),
            )}
        >
            {titulo}
        </span>
    );
}
