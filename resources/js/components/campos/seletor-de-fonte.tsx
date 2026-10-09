import { Check, ChevronsUpDown } from 'lucide-react';
import { useId, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { GruposFontes } from '@/types/certificados';

type Props = {
    valor: string;
    fontes: GruposFontes;
    aoEscolher: (familia: string) => void;
    desabilitado?: boolean;
    idDoRotulo: string;
};

export default function SeletorDeFonte({
    valor,
    fontes,
    aoEscolher,
    desabilitado,
    idDoRotulo,
}: Props) {
    const [aberto, setAberto] = useState(false);
    const idDoBotao = useId();

    return (
        <Popover open={aberto} onOpenChange={setAberto}>
            <PopoverTrigger asChild>
                <Button
                    id={idDoBotao}
                    variant="outline"
                    role="combobox"
                    aria-labelledby={`${idDoRotulo} ${idDoBotao}`}
                    aria-expanded={aberto}
                    disabled={desabilitado}
                    className="h-8 w-full justify-between px-2 text-xs font-normal"
                    style={{ fontFamily: `"${valor}"` }}
                >
                    <span className="truncate">{valor}</span>
                    <ChevronsUpDown className="size-3 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-56 p-0" align="start">
                <Command>
                    <CommandInput placeholder="Buscar fonte..." />
                    <CommandList>
                        <CommandEmpty>Nenhuma fonte encontrada.</CommandEmpty>
                        {Object.entries(fontes).map(([grupo, familias]) => (
                            <CommandGroup key={grupo} heading={grupo}>
                                {Object.keys(familias).map((familia) => (
                                    <CommandItem
                                        key={familia}
                                        value={familia}
                                        style={{ fontFamily: `"${familia}"` }}
                                        onSelect={() => {
                                            aoEscolher(familia);
                                            setAberto(false);
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                'size-3',
                                                familia === valor
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        {familia}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        ))}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
