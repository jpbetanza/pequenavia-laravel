import { Download, Trash2 } from 'lucide-react';
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
import type { LinhaDeGeracao } from '@/lib/geracao';
import { TAMANHO_MAXIMO_DO_VALOR } from '@/lib/planilha';
import type { Campo } from '@/types/certificados';

type Props = {
    campos: Campo[];
    linhas: LinhaDeGeracao[];
    aoEditar: (id: number, nome: string, valor: string) => void;
    aoGerar: (id: number) => void;
    aoBaixar: (id: number) => void;
    aoRemover: (id: number) => void;
};

export default function TabelaDeGeracao({
    campos,
    linhas,
    aoEditar,
    aoGerar,
    aoBaixar,
    aoRemover,
}: Props) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="w-px whitespace-nowrap">
                        Ação
                    </TableHead>
                    {campos.map((campo) => (
                        <TableHead key={campo.id}>{campo.nome}</TableHead>
                    ))}
                    <TableHead className="w-px">
                        <span className="sr-only">Excluir linha</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {linhas.map((linha) => (
                    <TableRow key={linha.id}>
                        <TableCell className="align-top whitespace-nowrap">
                            {linha.status === 'pronto' ? (
                                <Button
                                    size="sm"
                                    onClick={() => aoBaixar(linha.id)}
                                >
                                    <Download />
                                    Baixar
                                </Button>
                            ) : linha.status === 'erro' ? (
                                <>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        aria-describedby={
                                            linha.erro
                                                ? `erro-da-linha-${linha.id}`
                                                : undefined
                                        }
                                        onClick={() => aoGerar(linha.id)}
                                    >
                                        Tentar novamente
                                    </Button>
                                    {linha.erro && (
                                        <p
                                            id={`erro-da-linha-${linha.id}`}
                                            className="mt-1 max-w-48 text-xs whitespace-normal text-destructive"
                                        >
                                            {linha.erro}
                                        </p>
                                    )}
                                </>
                            ) : (
                                <Button
                                    size="sm"
                                    disabled={linha.status === 'gerando'}
                                    onClick={() => aoGerar(linha.id)}
                                >
                                    {linha.status === 'gerando' && <Spinner />}
                                    Gerar certificado
                                </Button>
                            )}
                        </TableCell>
                        {campos.map((campo) => (
                            <TableCell key={campo.id} className="align-top">
                                <Input
                                    className="h-8 min-w-32"
                                    aria-label={
                                        campo.label?.trim() || campo.nome
                                    }
                                    placeholder={
                                        campo.label?.trim() || campo.nome
                                    }
                                    maxLength={TAMANHO_MAXIMO_DO_VALOR}
                                    value={linha.valores[campo.nome] ?? ''}
                                    disabled={linha.status === 'gerando'}
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
                        <TableCell className="align-top">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 text-muted-foreground hover:text-destructive"
                                aria-label="Excluir linha"
                                disabled={linha.status === 'gerando'}
                                onClick={() => aoRemover(linha.id)}
                            >
                                <Trash2 />
                            </Button>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
