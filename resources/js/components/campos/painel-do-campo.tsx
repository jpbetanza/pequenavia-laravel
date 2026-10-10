import { Trash2 } from 'lucide-react';
import { useId } from 'react';
import CampoDeCor from '@/components/campos/campo-de-cor';
import CampoDeTexto from '@/components/campos/campo-de-texto';
import CampoNumerico from '@/components/campos/campo-numerico';
import SeletorDeFonte from '@/components/campos/seletor-de-fonte';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
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

export default function PainelDoCampo({
    campo,
    fontes,
    pesos,
    somenteLeitura,
    aoAtualizar,
    aoExcluir,
}: Props) {
    const idDoNome = useId();
    const idDaAjudaDoNome = useId();
    const idDoExemplo = useId();
    const idDaFonte = useId();
    const idDoX = useId();
    const idDoPosicao = useId();

    const atualizar = (mudancas: Partial<Campo>) =>
        aoAtualizar(campo.id, mudancas);

    const pesosDaFonte = pesosDaFamilia(
        fontes,
        pesos,
        campo.fonte_familia,
        campo.peso_fonte,
    );

    const escolherFonte = (familia: string) => {
        const doNovo = pesosDaFamilia(fontes, pesos, familia, campo.peso_fonte);

        atualizar({
            fonte_familia: familia,
            peso_fonte: pesoMaisProximo(doNovo, campo.peso_fonte),
        });
    };

    const rotuloDoPainel = `Campo “${campo.nome}”`;

    return (
        <aside
            aria-label="Propriedades do campo"
            className="flex flex-col gap-5 self-start rounded-xl border bg-card p-4"
        >
            <div className="flex items-center justify-between gap-2">
                <h2 className="min-w-0 truncate font-serif text-xl font-semibold">
                    {rotuloDoPainel}
                </h2>

                {!somenteLeitura && (
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-11 shrink-0 text-destructive"
                                aria-label="Excluir campo"
                            >
                                <Trash2 />
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Excluir o campo “{campo.nome}”?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    O campo sai do modelo. Esta ação não pode
                                    ser desfeita.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                <AlertDialogAction
                                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                    onClick={() => aoExcluir(campo.id)}
                                >
                                    Excluir campo
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                )}
            </div>

            <div className="flex flex-col gap-1.5">
                <Label htmlFor={idDoNome} className="text-xs">
                    Nome da coluna
                </Label>
                <CampoDeTexto
                    id={idDoNome}
                    descricaoId={idDaAjudaDoNome}
                    rotulo="Nome da coluna"
                    placeholder="Nome da coluna"
                    valor={campo.nome}
                    obrigatorio
                    desabilitado={somenteLeitura}
                    tamanhoMaximo={100}
                    aoConfirmar={(nome) => atualizar({ nome })}
                />
                <p
                    id={idDaAjudaDoNome}
                    className="text-xs text-muted-foreground"
                >
                    É o cabeçalho esperado na planilha.
                </p>
            </div>

            <div className="flex flex-col gap-1.5">
                <Label htmlFor={idDoExemplo} className="text-xs">
                    Texto de exemplo
                </Label>
                <CampoDeTexto
                    id={idDoExemplo}
                    rotulo="Texto de exemplo"
                    placeholder={campo.nome}
                    valor={campo.label ?? ''}
                    desabilitado={somenteLeitura}
                    tamanhoMaximo={150}
                    aoConfirmar={(label) =>
                        atualizar({ label: label === '' ? null : label })
                    }
                />
            </div>

            <div className="grid grid-cols-[minmax(0,1fr)_96px] gap-3">
                <div className="flex min-w-0 flex-col gap-1.5">
                    <Label id={idDaFonte} className="text-xs">
                        Fonte
                    </Label>
                    <SeletorDeFonte
                        valor={campo.fonte_familia}
                        fontes={fontes}
                        desabilitado={somenteLeitura}
                        idDoRotulo={idDaFonte}
                        aoEscolher={escolherFonte}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label className="text-xs">Peso</Label>
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
                            className="h-11 w-full text-xs"
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

            <div className="grid grid-cols-2 gap-3">
                <CampoNumerico
                    rotulo="Tamanho (px)"
                    valor={campo.tamanho_fonte}
                    min={4}
                    max={1000}
                    desabilitado={somenteLeitura}
                    aoConfirmar={(tamanho) =>
                        atualizar({ tamanho_fonte: Math.round(tamanho) })
                    }
                />
                <CampoNumerico
                    rotulo="Espaçamento (px)"
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

            <CampoDeCor
                valor={campo.cor}
                desabilitado={somenteLeitura}
                aoConfirmar={(cor) => atualizar({ cor })}
            />

            <div className="flex flex-col gap-1.5">
                <span
                    id={idDoPosicao}
                    className="text-xs text-muted-foreground"
                >
                    Posição
                </span>
                <div
                    role="group"
                    aria-labelledby={idDoPosicao}
                    className="grid grid-cols-2 rounded-lg border bg-background p-1"
                >
                    {[
                        { centralizado: false, rotulo: 'Livre' },
                        { centralizado: true, rotulo: 'Centralizado' },
                    ].map((opcao) => (
                        <button
                            key={opcao.rotulo}
                            type="button"
                            disabled={somenteLeitura}
                            aria-pressed={
                                campo.centralizado === opcao.centralizado
                            }
                            onClick={() =>
                                atualizar({ centralizado: opcao.centralizado })
                            }
                            className={cn(
                                'min-h-11 rounded-md text-sm transition-colors disabled:pointer-events-none disabled:opacity-50',
                                campo.centralizado === opcao.centralizado
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {opcao.rotulo}
                        </button>
                    ))}
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
                {campo.centralizado ? (
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={idDoX} className="text-xs">
                            X
                        </Label>
                        <Input
                            id={idDoX}
                            value="centro"
                            disabled
                            readOnly
                            className="h-11"
                        />
                    </div>
                ) : (
                    <CampoNumerico
                        rotulo="X"
                        valor={campo.x}
                        min={0}
                        max={10000}
                        desabilitado={somenteLeitura}
                        aoConfirmar={(x) => atualizar({ x: Math.round(x) })}
                    />
                )}
                <CampoNumerico
                    rotulo="Y"
                    valor={campo.y}
                    min={0}
                    max={10000}
                    desabilitado={somenteLeitura}
                    aoConfirmar={(y) => atualizar({ y: Math.round(y) })}
                />
            </div>
        </aside>
    );
}
