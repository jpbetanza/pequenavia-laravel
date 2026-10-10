import { CircleAlert, Download, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    emAndamento,
    linhaPreenchida,
    type LinhaDeGeracao,
} from '@/lib/geracao';
import { TAMANHO_MAXIMO_DO_VALOR } from '@/lib/planilha';
import { cn } from '@/lib/utils';
import type { Campo } from '@/types/certificados';

type Props = {
    campos: Campo[];
    linhas: LinhaDeGeracao[];
    selecionadaId: number | null;
    /** Durante "Gerar todos" os botões de gerar ficam parados. */
    geracaoBloqueada: boolean;
    aoSelecionar: (id: number) => void;
    aoEditar: (id: number, nome: string, valor: string) => void;
    aoGerar: (id: number) => void;
    aoBaixar: (id: number) => void;
    aoRemover: (id: number) => void;
    aoAdicionar: () => void;
};

function SituacaoDaLinha({
    linha,
    numero,
    geracaoBloqueada,
    aoGerar,
    aoBaixar,
}: {
    linha: LinhaDeGeracao;
    /** Número da linha na tabela (começa em 1), usado nos nomes acessíveis dos botões. */
    numero: number;
    geracaoBloqueada: boolean;
    aoGerar: (id: number) => void;
    aoBaixar: (id: number) => void;
}) {
    if (linha.status === 'pronto') {
        return (
            <Button
                size="sm"
                className="bg-sucesso-fundo text-sucesso hover:bg-sucesso-fundo/80"
                aria-label={`Baixar PNG da linha ${numero}`}
                onClick={() => aoBaixar(linha.id)}
            >
                <Download />
                Baixar PNG
            </Button>
        );
    }

    if (linha.status === 'gerando') {
        return (
            <span className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Spinner />
                Gerando…
            </span>
        );
    }

    if (linha.status === 'aguardando') {
        return (
            <span className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Spinner />
                Aguardando limite…
            </span>
        );
    }

    if (linha.status === 'na-fila') {
        return (
            <span className="text-sm font-medium text-muted-foreground">
                Na fila
            </span>
        );
    }

    if (linha.status === 'erro') {
        return (
            <div className="flex flex-col items-start gap-1.5">
                <p
                    id={`erro-da-linha-${linha.id}`}
                    className="flex items-start gap-1.5 text-xs whitespace-normal text-aviso"
                >
                    <CircleAlert className="mt-px size-3.5 shrink-0" />
                    {linha.erro}
                </p>
                <Button
                    size="sm"
                    variant="outline"
                    disabled={geracaoBloqueada}
                    aria-label={`Tentar novamente a linha ${numero}`}
                    aria-describedby={`erro-da-linha-${linha.id}`}
                    onClick={() => aoGerar(linha.id)}
                >
                    Tentar novamente
                </Button>
            </div>
        );
    }

    if (!linhaPreenchida(linha)) {
        return (
            <span className="flex items-center gap-1.5 text-sm font-medium text-aviso">
                <CircleAlert className="size-3.5" />
                Falta preencher
            </span>
        );
    }

    return (
        <Button
            size="sm"
            variant="secondary"
            disabled={geracaoBloqueada || emAndamento(linha)}
            aria-label={`Gerar linha ${numero}`}
            onClick={() => aoGerar(linha.id)}
        >
            Gerar
        </Button>
    );
}

export default function TabelaDeGeracao({
    campos,
    linhas,
    selecionadaId,
    geracaoBloqueada,
    aoSelecionar,
    aoEditar,
    aoGerar,
    aoBaixar,
    aoRemover,
    aoAdicionar,
}: Props) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="w-12">#</TableHead>
                    {campos.map((campo) => (
                        <TableHead key={campo.id}>{campo.nome}</TableHead>
                    ))}
                    <TableHead className="w-[170px]">Situação</TableHead>
                    <TableHead className="w-12">
                        <span className="sr-only">Excluir linha</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {linhas.map((linha, indice) => {
                    const selecionada = linha.id === selecionadaId;

                    return (
                        <TableRow
                            key={linha.id}
                            className={cn(
                                'transition-colors',
                                selecionada && 'bg-aviso-fundo',
                            )}
                            onFocus={() => aoSelecionar(linha.id)}
                            onClick={() => aoSelecionar(linha.id)}
                        >
                            <TableCell
                                className={cn(
                                    'align-middle text-muted-foreground tabular-nums',
                                    selecionada && 'font-semibold text-primary',
                                )}
                            >
                                {indice + 1}
                            </TableCell>
                            {campos.map((campo) => (
                                <TableCell key={campo.id} className="p-2">
                                    <Input
                                        className="h-10 border-transparent bg-transparent shadow-none focus-visible:border-primary focus-visible:bg-card"
                                        aria-label={`${campo.label?.trim() || campo.nome}, linha ${indice + 1}`}
                                        placeholder={
                                            campo.label?.trim() || campo.nome
                                        }
                                        maxLength={TAMANHO_MAXIMO_DO_VALOR}
                                        value={linha.valores[campo.nome] ?? ''}
                                        disabled={emAndamento(linha)}
                                        onChange={(evento) =>
                                            aoEditar(
                                                linha.id,
                                                campo.nome,
                                                evento.target.value,
                                            )
                                        }
                                    />
                                </TableCell>
                            ))}
                            <TableCell className="align-middle">
                                <SituacaoDaLinha
                                    linha={linha}
                                    numero={indice + 1}
                                    geracaoBloqueada={geracaoBloqueada}
                                    aoGerar={aoGerar}
                                    aoBaixar={aoBaixar}
                                />
                            </TableCell>
                            <TableCell className="align-middle">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="text-muted-foreground hover:text-destructive"
                                    aria-label={`Excluir linha ${indice + 1}`}
                                    disabled={emAndamento(linha)}
                                    onClick={() => aoRemover(linha.id)}
                                >
                                    <Trash2 />
                                </Button>
                            </TableCell>
                        </TableRow>
                    );
                })}
                <TableRow>
                    <TableCell colSpan={campos.length + 3} className="p-0">
                        <button
                            type="button"
                            onClick={aoAdicionar}
                            className="flex min-h-11 w-full items-center gap-2 px-4 text-sm font-medium text-primary transition-colors hover:bg-muted"
                        >
                            <Plus className="size-4" />
                            Adicionar linha
                        </button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    );
}
