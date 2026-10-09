import type { ReactNode } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

type Props = {
    aberto: boolean;
    aoFechar: () => void;
    titulo: string;
    rodape?: ReactNode;
    children: ReactNode;
};

export default function PainelDePreview({
    aberto,
    aoFechar,
    titulo,
    rodape,
    children,
}: Props) {
    return (
        <Sheet open={aberto} onOpenChange={(abrir) => !abrir && aoFechar()}>
            <SheetContent
                side="right"
                className="flex w-full flex-col gap-0 sm:max-w-[480px]"
            >
                <SheetHeader className="border-b">
                    <SheetTitle>{titulo}</SheetTitle>
                    <SheetDescription className="sr-only">
                        Pré-visualização do certificado
                    </SheetDescription>
                </SheetHeader>
                <div className="flex flex-1 flex-col items-center justify-center overflow-auto p-4">
                    {children}
                </div>
                {rodape && <div className="border-t p-4">{rodape}</div>}
            </SheetContent>
        </Sheet>
    );
}
