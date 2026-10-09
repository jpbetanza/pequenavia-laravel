import { AlignCenter, Trash2 } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import CampoDeTexto from '@/components/campos/campo-de-texto';
import CampoNumerico from '@/components/campos/campo-numerico';
import SeletorDeFonte from '@/components/campos/seletor-de-fonte';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Toggle } from '@/components/ui/toggle';
import { pesoMaisProximo, pesosDaFamilia, rotuloDoPeso } from '@/lib/fontes';
import type { Campo, GruposFontes, PesosDasFontes } from '@/types/certificados';

type Props = {
    campo: Campo;
    fontes: GruposFontes;
    pesos: PesosDasFontes;
    somenteLeitura: boolean;
    aoAtualizar: (id: string, mudancas: Partial<Campo>) => void;
    aoExcluir: (id: string) => void;
};

/**
 * O seletor de cor nativo dispara a cada movimento; só salva depois de uma pausa. Se o campo sair da
 * tela com uma cor ainda pendente, essa cor é salva na hora (uma única vez).
 */
function CampoDeCor({
    valor,
    aoConfirmar,
    desabilitado,
}: {
    valor: string;
    aoConfirmar: (cor: string) => void;
    desabilitado: boolean;
}) {
    const [rascunho, setRascunho] = useState<string | null>(null);
    const temporizador = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendente = useRef<string | null>(null);
    const confirmarRef = useRef(aoConfirmar);
    const id = useId();

    useEffect(() => {
        confirmarRef.current = aoConfirmar;
    });

    useEffect(
        () => () => {
            if (temporizador.current) {
                clearTimeout(temporizador.current);
                temporizador.current = null;
            }

            const cor = pendente.current;
            pendente.current = null;

            if (cor !== null) {
                confirmarRef.current(cor);
            }
        },
        [],
    );

    const mudar = (cor: string) => {
        setRascunho(cor);
        pendente.current = cor;

        if (temporizador.current) {
            clearTimeout(temporizador.current);
        }

        temporizador.current = setTimeout(() => {
            temporizador.current = null;

            const confirmar = pendente.current;
            pendente.current = null;
            setRascunho(null);

            if (confirmar !== null) {
                confirmarRef.current(confirmar);
            }
        }, 400);
    };

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={id} className="text-[10px] text-muted-foreground">
                Cor
            </Label>
            <div className="flex items-center gap-1.5">
                <input
                    id={id}
                    type="color"
                    value={rascunho ?? valor}
                    disabled={desabilitado}
                    onChange={(evento) => mudar(evento.target.value)}
                    className="h-8 w-10 cursor-pointer rounded border bg-background p-0.5 disabled:cursor-not-allowed"
                />
                <span className="font-mono text-[10px] text-muted-foreground">
                    {rascunho ?? valor}
                </span>
            </div>
        </div>
    );
}

export default function CartaoDoCampo({
    campo,
    fontes,
    pesos,
    somenteLeitura,
    aoAtualizar,
    aoExcluir,
}: Props) {
    const pesosDaFonte = pesosDaFamilia(
        fontes,
        pesos,
        campo.fonte_familia,
        campo.peso_fonte,
    );
    const atualizar = (mudancas: Partial<Campo>) =>
        aoAtualizar(campo.id, mudancas);

    const idDoRotuloTipografia = useId();

    const escolherFonte = (familia: string) => {
        const doNovo = pesosDaFamilia(fontes, pesos, familia, campo.peso_fonte);

        atualizar({
            fonte_familia: familia,
            peso_fonte: pesoMaisProximo(doNovo, campo.peso_fonte),
        });
    };

    return (
        <div className="space-y-3 rounded-lg border bg-card p-3">
            <p className="truncate text-xs font-medium text-muted-foreground">
                {campo.nome || 'Sem nome'}
            </p>

            <div className="flex items-center gap-2">
                <CampoDeTexto
                    rotulo="Nome do campo"
                    placeholder="Nome do campo"
                    valor={campo.nome}
                    obrigatorio
                    desabilitado={somenteLeitura}
                    tamanhoMaximo={100}
                    aoConfirmar={(nome) => atualizar({ nome })}
                />
                <CampoDeTexto
                    rotulo="Rótulo"
                    placeholder={campo.nome || 'Rótulo'}
                    valor={campo.label ?? ''}
                    desabilitado={somenteLeitura}
                    tamanhoMaximo={150}
                    aoConfirmar={(label) =>
                        atualizar({ label: label === '' ? null : label })
                    }
                />
                <Toggle
                    size="sm"
                    pressed={campo.centralizado}
                    disabled={somenteLeitura}
                    onPressedChange={(centralizado) =>
                        atualizar({ centralizado })
                    }
                    aria-label="Centralizar horizontalmente"
                    title="Centralizar horizontalmente"
                >
                    <AlignCenter />
                </Toggle>
                {!somenteLeitura && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8 text-destructive"
                        aria-label="Excluir campo"
                        onClick={() => aoExcluir(campo.id)}
                    >
                        <Trash2 />
                    </Button>
                )}
            </div>

            <div className="grid grid-cols-4 gap-2">
                <CampoNumerico
                    rotulo="X"
                    valor={campo.x}
                    min={0}
                    max={10000}
                    desabilitado={somenteLeitura || campo.centralizado}
                    aoConfirmar={(x) => atualizar({ x: Math.round(x) })}
                />
                <CampoNumerico
                    rotulo="Y"
                    valor={campo.y}
                    min={0}
                    max={10000}
                    desabilitado={somenteLeitura}
                    aoConfirmar={(y) => atualizar({ y: Math.round(y) })}
                />
                <CampoNumerico
                    rotulo="Fonte (px)"
                    valor={campo.tamanho_fonte}
                    min={4}
                    max={1000}
                    desabilitado={somenteLeitura}
                    aoConfirmar={(tamanho) =>
                        atualizar({ tamanho_fonte: Math.round(tamanho) })
                    }
                />
                <CampoNumerico
                    rotulo="Espaç. (px)"
                    valor={campo.espacamento_letras}
                    min={-50}
                    max={200}
                    passo={0.5}
                    desabilitado={somenteLeitura}
                    aoConfirmar={(espacamento) =>
                        atualizar({ espacamento_letras: espacamento })
                    }
                />
            </div>

            <div className="grid grid-cols-3 gap-2">
                <CampoDeCor
                    valor={campo.cor}
                    desabilitado={somenteLeitura}
                    aoConfirmar={(cor) => atualizar({ cor })}
                />
                <div className="flex flex-col gap-1">
                    <Label
                        id={idDoRotuloTipografia}
                        className="text-[10px] text-muted-foreground"
                    >
                        Tipografia
                    </Label>
                    <SeletorDeFonte
                        valor={campo.fonte_familia}
                        fontes={fontes}
                        desabilitado={somenteLeitura}
                        idDoRotulo={idDoRotuloTipografia}
                        aoEscolher={escolherFonte}
                    />
                </div>
                <div className="flex flex-col gap-1">
                    <Label className="text-[10px] text-muted-foreground">
                        Peso
                    </Label>
                    <Select
                        value={String(
                            pesoMaisProximo(pesosDaFonte, campo.peso_fonte),
                        )}
                        disabled={somenteLeitura}
                        onValueChange={(peso) =>
                            atualizar({ peso_fonte: Number(peso) })
                        }
                    >
                        <SelectTrigger
                            aria-label="Peso"
                            className="h-8 w-full text-xs"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {pesosDaFonte.map((peso) => (
                                <SelectItem key={peso} value={String(peso)}>
                                    {rotuloDoPeso(peso)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </div>
    );
}
