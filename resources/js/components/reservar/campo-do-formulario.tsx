import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

type Props = {
    /** Id do controle de formulário; o rótulo e a mensagem de erro se ligam a ele. */
    id: string;
    rotulo: ReactNode;
    erro?: string;
    dica?: ReactNode;
    children: ReactNode;
};

/** Rótulo, controle, dica e erro de um campo do formulário público. */
export default function CampoDoFormulario({
    id,
    rotulo,
    erro,
    dica,
    children,
}: Props) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id} className="text-sm font-semibold">
                {rotulo}
            </Label>
            {children}
            {dica}
            <InputError
                id={`${id}-erro`}
                message={erro}
                className="text-destructive"
            />
        </div>
    );
}
