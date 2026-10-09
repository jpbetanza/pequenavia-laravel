import { Card } from '@/components/ui/card';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    modelo: ModeloResumo;
    aoClicar: () => void;
};

export default function CartaoDeModelo({ modelo, aoClicar }: Props) {
    return (
        <Card
            role="button"
            tabIndex={0}
            className="cursor-pointer gap-0 overflow-hidden py-0 transition-shadow hover:shadow-lg"
            onClick={aoClicar}
            onKeyDown={(evento) => {
                if (evento.key === 'Enter' || evento.key === ' ') {
                    evento.preventDefault();
                    aoClicar();
                }
            }}
        >
            <div className="aspect-[4/3] w-full overflow-hidden bg-muted">
                <img
                    src={modelo.imagem_url}
                    alt={modelo.title}
                    loading="lazy"
                    className="size-full object-cover"
                />
            </div>
            <div className="flex flex-col items-center gap-1 p-4 text-center">
                <p className="line-clamp-1 text-sm font-semibold">
                    {modelo.title}
                </p>
                {modelo.description && (
                    <p className="line-clamp-2 text-xs text-muted-foreground">
                        {modelo.description}
                    </p>
                )}
            </div>
        </Card>
    );
}
