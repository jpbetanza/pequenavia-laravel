import { useState } from 'react';
import { Input } from '@/components/ui/input';

type Props = {
    valor: string;
    aoConfirmar: (valor: string) => void;
    placeholder?: string;
    rotulo: string;
    obrigatorio?: boolean;
    desabilitado?: boolean;
};

export default function CampoDeTexto({
    valor,
    aoConfirmar,
    placeholder,
    rotulo,
    obrigatorio,
    desabilitado,
}: Props) {
    const [rascunho, setRascunho] = useState<string | null>(null);

    const confirmar = () => {
        if (rascunho === null) {
            return;
        }

        const limpo = rascunho.trim();
        setRascunho(null);

        if ((obrigatorio && limpo === '') || limpo === valor) {
            return;
        }

        aoConfirmar(limpo);
    };

    return (
        <Input
            aria-label={rotulo}
            className="h-8 flex-1 px-2 text-xs"
            placeholder={placeholder}
            value={rascunho ?? valor}
            disabled={desabilitado}
            onChange={(evento) => setRascunho(evento.target.value)}
            onBlur={confirmar}
            onKeyDown={(evento) =>
                evento.key === 'Enter' && evento.currentTarget.blur()
            }
        />
    );
}
