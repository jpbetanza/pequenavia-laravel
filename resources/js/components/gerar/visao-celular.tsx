import { Link } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    CircleCheck,
    Download,
    FileUp,
    Plus,
} from 'lucide-react';
import type { ReactNode } from 'react';
import PreviewDoModelo from '@/components/campos/preview-do-modelo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
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
    aoBaixar: (id: number) => void;
    aoImportar: () => void;
    aoLimpar: () => void;
};

function StatusDaLinhaCelular({
    linha,
    aoBaixar,
}: {
    linha: LinhaDeGeracao | null;
    aoBaixar: (id: number) => void;
}) {
    // A região fica sempre montada: o leitor de tela anuncia só as mudanças de texto.
    let conteudo: ReactNode = null;

    if (linha?.status === 'gerando') {
        conteudo = (
            <span className="flex items-center gap-2 font-medium text-muted-foreground">
                <Spinner aria-hidden="true" />
                Gerando…
            </span>
        );
    } else if (linha?.status === 'aguardando') {
        conteudo = (
            <span className="flex items-center gap-2 font-medium text-muted-foreground">
                <Spinner aria-hidden="true" />
                Aguardando limite…
            </span>
        );
    } else if (linha?.status === 'na-fila') {
        conteudo = (
            <span className="font-medium text-muted-foreground">Na fila</span>
        );
    } else if (linha?.status === 'pronto') {
        conteudo = (
            <span className="flex flex-wrap items-center gap-x-2 font-medium text-sucesso">
                <CircleCheck className="size-4 shrink-0" aria-hidden="true" />
                Pronto ·{' '}
                <button
                    type="button"
                    className="font-semibold text-primary underline"
                    onClick={() => aoBaixar(linha.id)}
                >
                    Baixar PNG
                </button>
            </span>
        );
    } else if (linha?.status === 'erro' && linha.erro) {
        conteudo = (
            <span className="flex items-start gap-1.5 text-aviso">
                <CircleAlert
                    className="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                {linha.erro}
            </span>
        );
    }

    return (
        <div aria-live="polite" className="min-h-6 text-sm">
            {conteudo}
        </div>
    );
}

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
    aoBaixar,
    aoImportar,
    aoLimpar,
}: Props) {
    const podeGerar =
        linha !== null &&
        linhaPreenchida(linha) &&
        !emAndamento(linha) &&
        !geracaoBloqueada;

    return (
        <div className="flex flex-col gap-4 pb-32">
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
                    disabled={algumGerando}
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
                </form>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Nenhum registro. Adicione uma linha para começar.
                </p>
            )}

            <StatusDaLinhaCelular linha={linha} aoBaixar={aoBaixar} />

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
