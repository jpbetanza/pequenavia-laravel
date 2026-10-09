import { useId, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    rotulo: string;
    valor: number;
    aoConfirmar: (valor: number) => void;
    min?: number;
    max?: number;
    passo?: number;
    desabilitado?: boolean;
};

export default function CampoNumerico({
    rotulo,
    valor,
    aoConfirmar,
    min,
    max,
    passo,
    desabilitado,
}: Props) {
    const [rascunho, setRascunho] = useState<string | null>(null);
    const id = useId();

    const confirmar = () => {
        if (rascunho === null) {
            return;
        }

        const digitado = Number(rascunho);
        setRascunho(null);

        if (rascunho.trim() === '' || !Number.isFinite(digitado)) {
            return;
        }

        const passoDaCasa = passo ?? 1;
        const arredondado = Math.round(digitado / passoDaCasa) * passoDaCasa;
        const limitado = Math.min(
            max ?? Infinity,
            Math.max(min ?? -Infinity, arredondado),
        );

        if (limitado !== valor) {
            aoConfirmar(limitado);
        }
    };

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={id} className="text-[10px] text-muted-foreground">
                {rotulo}
            </Label>
            <Input
                id={id}
                type="number"
                className="h-8 px-2 text-xs"
                value={rascunho ?? String(valor)}
                min={min}
                max={max}
                step={passo}
                disabled={desabilitado}
                onChange={(evento) => setRascunho(evento.target.value)}
                onBlur={confirmar}
                onKeyDown={(evento) =>
                    evento.key === 'Enter' && evento.currentTarget.blur()
                }
            />
        </div>
    );
}
