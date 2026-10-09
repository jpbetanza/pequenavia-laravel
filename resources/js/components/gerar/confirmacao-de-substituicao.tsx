import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

type Props = {
    aberto: boolean;
    quantidadeDeLinhas: number;
    aoConfirmar: () => void;
    aoCancelar: () => void;
};

export default function ConfirmacaoDeSubstituicao({
    aberto,
    quantidadeDeLinhas,
    aoConfirmar,
    aoCancelar,
}: Props) {
    return (
        <AlertDialog
            open={aberto}
            onOpenChange={(abrir) => !abrir && aoCancelar()}
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Substituir os registros atuais?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        A tabela já tem dados preenchidos ou certificados
                        gerados. Eles serão apagados e substituídos pelas{' '}
                        {quantidadeDeLinhas}{' '}
                        {quantidadeDeLinhas === 1 ? 'linha' : 'linhas'} da
                        planilha.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction onClick={aoConfirmar}>
                        Substituir
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
