import { useState } from 'react';
import { Input } from '@/components/ui/input';

type Props = {
    valor: string;
    aoConfirmar: (valor: string) => void;
    placeholder?: string;
    rotulo: string;
    obrigatorio?: boolean;
    desabilitado?: boolean;
    tamanhoMaximo?: number;
    id?: string;
    descricaoId?: string;
};

export default function CampoDeTexto({
    valor,
    aoConfirmar,
    placeholder,
    rotulo,
    obrigatorio,
    desabilitado,
    tamanhoMaximo,
    id,
    descricaoId,
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
            id={id}
            aria-describedby={descricaoId}
            aria-label={rotulo}
            className="h-8 flex-1 px-2 text-xs"
            placeholder={placeholder}
            value={rascunho ?? valor}
            disabled={desabilitado}
            maxLength={tamanhoMaximo}
            onChange={(evento) => setRascunho(evento.target.value)}
            onBlur={confirmar}
            onKeyDown={(evento) =>
                evento.key === 'Enter' && evento.currentTarget.blur()
            }
        />
    );
}
