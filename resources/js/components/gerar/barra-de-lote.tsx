import { Archive, Square, Zap } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    prontas: number;
    preenchidas: number;
    percentual: number;
    gerandoLote: boolean;
    temParaGerar: boolean;
    aoGerarTodos: () => void;
    aoParar: () => void;
    aoBaixarZip: () => void;
};

export default function BarraDeLote({
    prontas,
    preenchidas,
    percentual,
    gerandoLote,
    temParaGerar,
    aoGerarTodos,
    aoParar,
    aoBaixarZip,
}: Props) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-4 border-t bg-muted p-4">
            <div className="flex min-w-56 flex-1 flex-col gap-2">
                <span className="text-sm font-semibold">
                    {prontas} de {preenchidas} prontos
                </span>
                <div
                    role="progressbar"
                    aria-label="Certificados prontos"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={percentual}
                    className="h-1.5 overflow-hidden rounded-full bg-border"
                >
                    <div
                        className="h-full bg-primary transition-[width] duration-300"
                        style={{ width: `${percentual}%` }}
                    />
                </div>
            </div>
            <div className="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    className="min-h-11"
                    disabled={prontas === 0}
                    onClick={aoBaixarZip}
                >
                    <Archive />
                    Baixar prontos (.zip)
                </Button>
                {gerandoLote ? (
                    <Button
                        variant="outline"
                        className="min-h-11"
                        onClick={aoParar}
                    >
                        <Square />
                        Parar
                    </Button>
                ) : (
                    <Button
                        className="min-h-11"
                        disabled={!temParaGerar}
                        onClick={aoGerarTodos}
                    >
                        <Zap />
                        Gerar todos
                    </Button>
                )}
            </div>
        </div>
    );
}
