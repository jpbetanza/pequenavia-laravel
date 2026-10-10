import { Link } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    Download,
    FileUp,
    Plus,
} from 'lucide-react';
import PreviewDoModelo from '@/components/campos/preview-do-modelo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    emAndamento,
    linhaPreenchida,
    type LinhaDeGeracao,
} from '@/lib/geracao';
import { TAMANHO_MAXIMO_DO_VALOR } from '@/lib/planilha';
import { index as historico } from '@/routes/historico';
import { index as modelos } from '@/routes/meus-certificados';
import type { Campo, ModeloBasico } from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    campos: Campo[];
    linha: LinhaDeGeracao | null;
    posicao: number;
    total: number;
    geradosNaSessao: number;
    algumGerando: boolean;
    geracaoBloqueada: boolean;
    aoEditar: (id: number, nome: string, valor: string) => void;
    aoAnterior: () => void;
    aoProxima: () => void;
    aoAdicionar: () => void;
    aoGerarEBaixar: (id: number) => void;
    aoImportar: () => void;
    aoLimpar: () => void;
};

/** Gerar no celular (abaixo de md): prévia no topo, formulário empilhado e ações fixas embaixo. */
export default function VisaoCelular({
    modelo,
    campos,
    linha,
    posicao,
    total,
    geradosNaSessao,
    algumGerando,
    geracaoBloqueada,
    aoEditar,
    aoAnterior,
    aoProxima,
    aoAdicionar,
    aoGerarEBaixar,
    aoImportar,
    aoLimpar,
}: Props) {
    const podeGerar =
        linha !== null &&
        linhaPreenchida(linha) &&
        !emAndamento(linha) &&
        !geracaoBloqueada;

    return (
        <div className="flex flex-col gap-4 pb-32 md:hidden">
            <div className="flex items-center gap-2">
                <Link
                    href={modelos()}
                    aria-label="Voltar para Modelos"
                    className="flex size-11 items-center justify-center rounded-md text-foreground"
                >
                    <ChevronLeft className="size-6" aria-hidden="true" />
                </Link>
                <div className="flex min-w-0 flex-1 flex-col leading-tight">
                    <span className="text-xs text-muted-foreground">
                        Gerar certificado
                    </span>
                    <span className="truncate font-serif text-2xl font-semibold">
                        {modelo.title}
                    </span>
                </div>
                <Button
                    variant="outline"
                    size="icon"
                    className="size-11"
                    aria-label="Importar planilha"
                    onClick={aoImportar}
                >
                    <FileUp />
                </Button>
            </div>

            <PreviewDoModelo
                modelo={modelo}
                campos={campos}
                somenteLeitura
                arrastando={null}
                aoIniciarArraste={() => undefined}
                valores={linha?.valores}
            />

            {linha ? (
                <form
                    className="flex flex-col gap-3"
                    onSubmit={(evento) => evento.preventDefault()}
                >
                    {campos.map((campo) => {
                        const rotulo = campo.label?.trim() || campo.nome;

                        return (
                            <label
                                key={campo.id}
                                className="flex flex-col gap-1.5 text-sm font-semibold"
                            >
                                {rotulo}
                                <Input
                                    className="h-12 text-base"
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
                            </label>
                        );
                    })}
                    {linha.status === 'erro' && linha.erro && (
                        <p className="text-sm text-aviso">{linha.erro}</p>
                    )}
                </form>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Nenhum registro. Adicione uma linha para começar.
                </p>
            )}

            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-11"
                        aria-label="Registro anterior"
                        disabled={posicao <= 1}
                        onClick={aoAnterior}
                    >
                        <ChevronLeft />
                    </Button>
                    <span className="min-w-28 text-center text-sm font-medium tabular-nums">
                        Registro {posicao} de {total}
                    </span>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-11"
                        aria-label="Próximo registro"
                        disabled={posicao >= total}
                        onClick={aoProxima}
                    >
                        <ChevronRight />
                    </Button>
                </div>
                <Button
                    variant="outline"
                    size="icon"
                    className="size-11"
                    aria-label="Adicionar registro"
                    disabled={algumGerando}
                    onClick={aoAdicionar}
                >
                    <Plus />
                </Button>
            </div>

            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <CircleCheck className="size-4 shrink-0 text-sucesso" />
                {geradosNaSessao}{' '}
                {geradosNaSessao === 1
                    ? 'certificado gerado'
                    : 'certificados gerados'}{' '}
                nesta sessão ·{' '}
                <Link
                    href={historico()}
                    className="font-medium text-primary underline"
                >
                    ver
                </Link>
            </p>

            <div className="fixed inset-x-0 bottom-0 z-20 flex gap-2.5 border-t bg-card px-4 pt-3 pb-6">
                <Button
                    variant="outline"
                    className="h-13 shrink-0 px-5 text-base"
                    disabled={algumGerando}
                    onClick={aoLimpar}
                >
                    Limpar
                </Button>
                <Button
                    className="h-13 flex-1 text-base"
                    disabled={!podeGerar}
                    onClick={() => linha && aoGerarEBaixar(linha.id)}
                >
                    <Download />
                    Gerar e baixar
                </Button>
            </div>
        </div>
    );
}
