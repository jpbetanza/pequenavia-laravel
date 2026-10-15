import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/input-error';
import { corrigir } from '@/routes/gestao/conversas';
import type { ConversaSelecionada, OpcoesDeConversas } from '@/types/gestao';

type Props = {
    conversa: ConversaSelecionada;
    opcoes: OpcoesDeConversas;
    aberto: boolean;
    aoFechar: () => void;
};

/** Valor do select que representa "sem valor": volta ao do JEV (caso e resultado) ou nenhum tema. */
const SEM_VALOR = '__sem_valor__';

type Campos = {
    caso_de_uso: string | null;
    resultado: string | null;
    tema_id: string | null;
};

function Formulario({ conversa, opcoes, aoFechar }: Omit<Props, 'aberto'>) {
    const iniciais: Campos = {
        caso_de_uso: conversa.caso?.valor ?? null,
        resultado: conversa.resultado?.valor ?? null,
        tema_id: conversa.tema?.id ?? null,
    };
    const formulario = useForm<Campos>(iniciais);

    // Só vai para o servidor o que mudou: um campo não tocado continua como o JEV ou a correção anterior o deixou.
    const alterados = (dados: Campos) =>
        Object.fromEntries(
            Object.entries(dados).filter(
                ([campo, valor]) => valor !== iniciais[campo as keyof Campos],
            ),
        );
    const temAlteracao = Object.keys(alterados(formulario.data)).length > 0;

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();
        formulario.transform((dados) => alterados(dados));
        formulario.patch(corrigir.url({ conversa: conversa.id }), {
            preserveScroll: true,
            onSuccess: aoFechar,
        });
    };

    return (
        <form onSubmit={enviar} className="grid gap-4">
            <DialogHeader>
                <DialogTitle>Corrigir classificação</DialogTitle>
                <DialogDescription>
                    A correção fica registrada à parte e passa a valer no lugar
                    da leitura do JEV. Escolha só o que estiver errado.
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-2">
                <Label htmlFor="corrigir-caso">Caso de uso</Label>
                <Select
                    value={formulario.data.caso_de_uso ?? SEM_VALOR}
                    onValueChange={(valor) =>
                        formulario.setData(
                            'caso_de_uso',
                            valor === SEM_VALOR ? null : valor,
                        )
                    }
                >
                    <SelectTrigger
                        id="corrigir-caso"
                        className="min-h-11 w-full sm:min-h-9"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={SEM_VALOR}>
                            Manter o do JEV
                        </SelectItem>
                        {opcoes.casos.map((opcao) => (
                            <SelectItem key={opcao.valor} value={opcao.valor}>
                                {opcao.rotulo}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={formulario.errors.caso_de_uso} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="corrigir-resultado">Resultado</Label>
                <Select
                    value={formulario.data.resultado ?? SEM_VALOR}
                    onValueChange={(valor) =>
                        formulario.setData(
                            'resultado',
                            valor === SEM_VALOR ? null : valor,
                        )
                    }
                >
                    <SelectTrigger
                        id="corrigir-resultado"
                        className="min-h-11 w-full sm:min-h-9"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={SEM_VALOR}>
                            Manter o do JEV
                        </SelectItem>
                        {opcoes.resultados.map((opcao) => (
                            <SelectItem key={opcao.valor} value={opcao.valor}>
                                {opcao.rotulo}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={formulario.errors.resultado} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="corrigir-tema">Pedido não atendido</Label>
                <Select
                    value={formulario.data.tema_id ?? SEM_VALOR}
                    onValueChange={(valor) =>
                        formulario.setData(
                            'tema_id',
                            valor === SEM_VALOR ? null : valor,
                        )
                    }
                >
                    <SelectTrigger
                        id="corrigir-tema"
                        className="min-h-11 w-full sm:min-h-9"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={SEM_VALOR}>Nenhum tema</SelectItem>
                        {opcoes.temas.map((tema) => (
                            <SelectItem key={tema.id} value={tema.id}>
                                {tema.nome}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={formulario.errors.tema_id} />
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={aoFechar}
                    disabled={formulario.processing}
                    className="min-h-11 sm:min-h-9"
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    disabled={!temAlteracao || formulario.processing}
                    className="min-h-11 sm:min-h-9"
                >
                    {formulario.processing && <Spinner />}
                    Salvar correção
                </Button>
            </DialogFooter>
        </form>
    );
}

/** Corrige caso, resultado ou tema de uma conversa. O formulário nasce a cada abertura, com os valores atuais. */
export function DialogoCorrigir({ aberto, ...props }: Props) {
    return (
        <Dialog
            open={aberto}
            onOpenChange={(abrir) => !abrir && props.aoFechar()}
        >
            <DialogContent>
                {aberto && <Formulario key={props.conversa.id} {...props} />}
            </DialogContent>
        </Dialog>
    );
}
