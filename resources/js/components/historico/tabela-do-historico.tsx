import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    formatarData,
    ordenarValores,
    resumoDosValores,
} from '@/lib/historico';
import type { Geracao } from '@/types/certificados';

type Props = {
    geracoes: Geracao[];
    aoVerDetalhes: (geracao: Geracao) => void;
};

export default function TabelaDoHistorico({ geracoes, aoVerDetalhes }: Props) {
    return (
        <Table>
            <TableHeader>
                <TableRow className="bg-primary hover:bg-primary">
                    <TableHead className="text-primary-foreground">
                        MODELO
                    </TableHead>
                    <TableHead className="text-primary-foreground">
                        CAMPOS
                    </TableHead>
                    <TableHead className="text-primary-foreground">
                        DATA
                    </TableHead>
                    <TableHead className="w-px">
                        <span className="sr-only">Detalhes</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {geracoes.map((geracao) => (
                    <TableRow key={geracao.id}>
                        <TableCell className="font-medium whitespace-nowrap">
                            {geracao.modelo_title}
                        </TableCell>
                        <TableCell>
                            <div className="max-w-sm truncate text-muted-foreground">
                                {resumoDosValores(
                                    ordenarValores(
                                        geracao.campos_valores,
                                        geracao.ordem_campos,
                                    ),
                                )}
                            </div>
                        </TableCell>
                        <TableCell className="whitespace-nowrap text-muted-foreground">
                            {formatarData(geracao.created_at)}
                        </TableCell>
                        <TableCell>
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => aoVerDetalhes(geracao)}
                            >
                                Detalhes
                            </Button>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
