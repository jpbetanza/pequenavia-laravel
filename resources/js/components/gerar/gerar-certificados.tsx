import { Link } from '@inertiajs/react';
import { Download, Eye, FileUp, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Http/Controllers/GeracaoCertificadoController';
import PreviewDoModelo from '@/components/campos/preview-do-modelo';
import DialogoDeImportacao from '@/components/gerar/dialogo-de-importacao';
import PainelDePreview from '@/components/gerar/painel-de-preview';
import TabelaDeGeracao from '@/components/gerar/tabela-de-geracao';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useFontesDoCatalogo } from '@/hooks/use-fontes-do-catalogo';
import {
    editarCelula,
    gerarCertificado,
    marcarErro,
    marcarGerando,
    marcarPronto,
    novaLinha,
    removerLinha,
    substituirLinhas,
    urlsDasLinhas,
    type LinhaDeGeracao,
} from '@/lib/geracao';
import { mensagemDoErro } from '@/lib/http';
import { criarPlanilhaModelo, lerPlanilha } from '@/lib/planilha';
import { campos as rotaDeCampos } from '@/routes/meus-certificados';
import type {
    Campo,
    GruposFontes,
    ModeloBasico,
    PesosDasFontes,
} from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    campos: Campo[];
    fontes: GruposFontes;
    pesos: PesosDasFontes;
};

function baixarArquivo(url: string, nome: string) {
    const ancora = document.createElement('a');
    ancora.href = url;
    ancora.download = nome;
    document.body.appendChild(ancora);
    ancora.click();
    ancora.remove();
}

function revogar(linha: LinhaDeGeracao | undefined) {
    if (linha?.arquivo) {
        URL.revokeObjectURL(linha.arquivo.url);
    }
}

export default function GerarCertificados({
    modelo,
    campos,
    fontes,
    pesos,
}: Props) {
    useFontesDoCatalogo(fontes, pesos);

    const [linhas, setLinhas] = useState<LinhaDeGeracao[]>(() => [
        novaLinha(1, campos),
    ]);
    const proximoId = useRef(2);
    const [previewDaLinha, setPreviewDaLinha] = useState<number | null>(null);
    const [previewDoModelo, setPreviewDoModelo] = useState(false);
    const [importando, setImportando] = useState(false);
    const [lendoPlanilha, setLendoPlanilha] = useState(false);

    // Linhas em geração: impede o duplo clique de disparar a mesma requisição duas vezes.
    const geracoesEmAndamento = useRef(new Set<number>());
    const montado = useRef(false);
    const linhasAtuais = useRef(linhas);

    const algumGerando = linhas.some((linha) => linha.status === 'gerando');

    useEffect(() => {
        linhasAtuais.current = linhas;
    });

    useEffect(() => {
        montado.current = true;

        return () => {
            montado.current = false;
            urlsDasLinhas(linhasAtuais.current).forEach((url) =>
                URL.revokeObjectURL(url),
            );
        };
    }, []);

    const linhaEmPreview =
        linhas.find((linha) => linha.id === previewDaLinha) ?? null;

    const gerar = async (id: number) => {
        const linha = linhas.find((atual) => atual.id === id);

        if (!linha || geracoesEmAndamento.current.has(id)) {
            return;
        }

        geracoesEmAndamento.current.add(id);
        revogar(linha);
        setPreviewDoModelo(false);
        setPreviewDaLinha(id);
        setLinhas((atuais) => marcarGerando(atuais, id));

        try {
            const arquivo = await gerarCertificado(
                store.url({ modelo: modelo.id }),
                linha.valores,
                {
                    nomePadrao: `${modelo.title}.png`,
                },
            );

            // Se a página saiu do ar enquanto gerava, não cria a URL (ela nunca seria revogada).
            if (!montado.current) {
                return;
            }

            const url = URL.createObjectURL(arquivo.blob);

            setLinhas((atuais) =>
                marcarPronto(atuais, id, {
                    url,
                    nome: arquivo.nome,
                    tipo: arquivo.tipo,
                }),
            );
            baixarArquivo(url, arquivo.nome);
        } catch (erro) {
            const mensagem = mensagemDoErro(erro);

            if (montado.current) {
                setLinhas((atuais) => marcarErro(atuais, id, mensagem));
            }

            toast.error(mensagem);
        } finally {
            geracoesEmAndamento.current.delete(id);
        }
    };

    const baixar = (id: number) => {
        const arquivo = linhas.find((linha) => linha.id === id)?.arquivo;

        if (arquivo) {
            baixarArquivo(arquivo.url, arquivo.nome);
        }
    };

    const editar = (id: number, nome: string, valor: string) => {
        revogar(linhas.find((linha) => linha.id === id));
        setLinhas((atuais) => editarCelula(atuais, id, nome, valor));
    };

    const adicionarLinha = () => {
        const id = proximoId.current++;

        setLinhas((atuais) => [...atuais, novaLinha(id, campos)]);
    };

    const remover = (id: number) => {
        revogar(linhas.find((linha) => linha.id === id));
        setPreviewDaLinha((atual) => (atual === id ? null : atual));
        setLinhas((atuais) => removerLinha(atuais, id));
    };

    const limpar = () => {
        if (algumGerando) {
            return;
        }

        urlsDasLinhas(linhas).forEach((url) => URL.revokeObjectURL(url));
        setPreviewDaLinha(null);
        setLinhas([novaLinha(proximoId.current++, campos)]);
    };

    const importar = async (arquivo: File) => {
        if (algumGerando) {
            return;
        }

        setLendoPlanilha(true);

        try {
            const importadas = lerPlanilha(await arquivo.arrayBuffer(), campos);

            if (importadas.length === 0) {
                toast.error('A planilha não tem linhas de dados.');

                return;
            }

            urlsDasLinhas(linhas).forEach((url) => URL.revokeObjectURL(url));
            const primeiro = proximoId.current;
            proximoId.current += importadas.length;
            setLinhas(substituirLinhas(importadas, primeiro));
            setPreviewDaLinha(null);
            setImportando(false);
            toast.success(
                `${importadas.length} ${importadas.length === 1 ? 'linha importada' : 'linhas importadas'}.`,
            );
        } catch {
            toast.error(
                'Não foi possível ler a planilha. Confira se é um arquivo .xlsx ou .xls válido.',
            );
        } finally {
            setLendoPlanilha(false);
        }
    };

    const baixarPlanilhaModelo = () => {
        const bytes = criarPlanilhaModelo(campos);
        const url = URL.createObjectURL(
            new Blob([bytes], {
                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            }),
        );

        baixarArquivo(url, `modelo-${modelo.title}.xlsx`);
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    };

    const fecharPreview = () => {
        setPreviewDaLinha(null);
        setPreviewDoModelo(false);
    };

    return (
        <div className="flex flex-col gap-4">
            {campos.length === 0 && (
                <Alert>
                    <AlertDescription>
                        Este modelo ainda não tem campos.{' '}
                        <Link
                            href={rotaDeCampos({ modelo: modelo.id })}
                            className="font-medium underline"
                        >
                            Configure os campos
                        </Link>{' '}
                        antes de gerar certificados.
                    </AlertDescription>
                </Alert>
            )}

            <div className="flex flex-wrap items-center justify-between gap-2">
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => {
                        setPreviewDoModelo(true);
                        setPreviewDaLinha(null);
                    }}
                >
                    <Eye />
                    Ver preview
                </Button>
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        disabled={algumGerando}
                        onClick={() => setImportando(true)}
                    >
                        <FileUp />
                        Importar dados
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        disabled={algumGerando}
                        onClick={limpar}
                    >
                        <Trash2 />
                        Limpar
                    </Button>
                </div>
            </div>

            <TabelaDeGeracao
                campos={campos}
                linhas={linhas}
                aoEditar={editar}
                aoGerar={(id) => void gerar(id)}
                aoBaixar={baixar}
                aoRemover={remover}
            />

            <button
                type="button"
                onClick={adicionarLinha}
                className="w-full rounded border border-dashed py-2 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-primary"
            >
                + Adicionar linha
            </button>

            <DialogoDeImportacao
                aberto={importando}
                ocupado={lendoPlanilha}
                aoFechar={() => setImportando(false)}
                aoEscolherArquivo={(arquivo) => void importar(arquivo)}
                aoBaixarModelo={baixarPlanilhaModelo}
            />

            <PainelDePreview
                aberto={previewDoModelo || previewDaLinha !== null}
                aoFechar={fecharPreview}
                titulo={
                    previewDoModelo
                        ? 'Preview do template'
                        : 'Preview do certificado'
                }
                rodape={
                    !previewDoModelo && linhaEmPreview?.status === 'pronto' ? (
                        <Button
                            className="w-full"
                            onClick={() => baixar(linhaEmPreview.id)}
                        >
                            <Download />
                            Baixar
                        </Button>
                    ) : undefined
                }
            >
                {previewDoModelo && (
                    <PreviewDoModelo
                        modelo={modelo}
                        campos={campos}
                        somenteLeitura
                        arrastando={null}
                        aoIniciarArraste={() => undefined}
                    />
                )}
                {!previewDoModelo && linhaEmPreview?.status === 'gerando' && (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner />
                        Gerando certificado…
                    </p>
                )}
                {!previewDoModelo && linhaEmPreview?.status === 'erro' && (
                    <p className="text-sm text-destructive">
                        {linhaEmPreview.erro ?? 'Erro ao gerar o certificado.'}
                    </p>
                )}
                {!previewDoModelo &&
                    linhaEmPreview?.status === 'pronto' &&
                    linhaEmPreview.arquivo && (
                        <img
                            src={linhaEmPreview.arquivo.url}
                            alt="Preview do certificado"
                            className="max-h-full w-full rounded object-contain"
                        />
                    )}
            </PainelDePreview>
        </div>
    );
}
