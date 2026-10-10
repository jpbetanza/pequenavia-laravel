import { Link } from '@inertiajs/react';
import { Download, FileUp, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Http/Controllers/GeracaoCertificadoController';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import BarraDeLote from '@/components/gerar/barra-de-lote';
import ConfirmacaoDeSubstituicao from '@/components/gerar/confirmacao-de-substituicao';
import DialogoDeImportacao from '@/components/gerar/dialogo-de-importacao';
import PreviaDaLinha from '@/components/gerar/previa-da-linha';
import TabelaDeGeracao from '@/components/gerar/tabela-de-geracao';
import VisaoCelular from '@/components/gerar/visao-celular';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useFontesDoCatalogo } from '@/hooks/use-fontes-do-catalogo';
import { useIsMobile } from '@/hooks/use-mobile';
import {
    editarCelula,
    ehCancelamento,
    emAndamento,
    gerarCertificado,
    linhaPreenchida,
    linhasParaGerarTodas,
    marcarErro,
    marcarGerando,
    marcarPronto,
    mudarStatus,
    novaLinha,
    progresso,
    removerLinha,
    substituirLinhas,
    tabelaTemDados,
    urlsDasLinhas,
    valoresVazios,
    type ArquivoGerado,
    type LinhaDeGeracao,
} from '@/lib/geracao';
import { mensagemDoErro } from '@/lib/http';
import {
    cabecalhosFaltando,
    criarPlanilhaModelo,
    LIMITE_DE_LINHAS,
    type LeituraDaPlanilha,
    lerPlanilhaComAviso,
    nomeDaPlanilhaModelo,
} from '@/lib/planilha';
import { nomeDoZip, zipar } from '@/lib/zip';
import { campos as rotaDeCampos } from '@/routes/meus-certificados';
import type {
    Campo,
    GruposFontes,
    ModeloBasico,
    PesosDasFontes,
    ValoresIniciais,
} from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    campos: Campo[];
    fontes: GruposFontes;
    pesos: PesosDasFontes;
    /** Valores de uma geração anterior (`?geracao=`): a primeira linha já vem preenchida. */
    valoresIniciais: ValoresIniciais;
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
    valoresIniciais,
}: Props) {
    useFontesDoCatalogo(fontes, pesos);
    // Celular e desktop montam uma só visão: a prévia não existe duas vezes na árvore.
    const celular = useIsMobile();

    const [linhas, setLinhas] = useState<LinhaDeGeracao[]>(() => [
        novaLinha(
            1,
            campos,
            valoresIniciais
                ? { ...valoresVazios(campos), ...valoresIniciais }
                : undefined,
        ),
    ]);
    // Espelho síncrono de `linhas`: as gerações em sequência leem o valor atual sem esperar o render.
    const linhasRef = useRef(linhas);
    const proximoId = useRef(2);
    const linhaParaFocar = useRef<number | null>(null);
    const [selecionadaId, setSelecionadaId] = useState<number | null>(1);
    const [importando, setImportando] = useState(false);
    const [lendoPlanilha, setLendoPlanilha] = useState(false);
    const [geradosNaSessao, setGeradosNaSessao] = useState(0);
    // Linhas importadas esperando a confirmação de que podem substituir a tabela atual.
    const [substituicaoPendente, setSubstituicaoPendente] =
        useState<LeituraDaPlanilha | null>(null);
    const [gerandoLote, setGerandoLote] = useState(false);

    // Linhas em geração: impede o duplo clique de disparar a mesma requisição duas vezes.
    const geracoesEmAndamento = useRef(new Set<number>());
    const montado = useRef(false);
    // Um controlador por montagem: ao sair da página, cancela as gerações em andamento.
    const controle = useRef<AbortController | null>(null);
    // Controlador de "Gerar todos": "Parar" aborta só o lote, e sair da página aborta também.
    const controleDoLote = useRef<AbortController | null>(null);

    useEffect(() => {
        montado.current = true;

        const controlador = new AbortController();
        controle.current = controlador;

        return () => {
            montado.current = false;
            controlador.abort();
            urlsDasLinhas(linhasRef.current).forEach((url) =>
                URL.revokeObjectURL(url),
            );
        };
    }, []);

    const atualizar = (
        mudar: (atuais: LinhaDeGeracao[]) => LinhaDeGeracao[],
    ) => {
        const proximas = mudar(linhasRef.current);

        linhasRef.current = proximas;
        setLinhas(proximas);
    };

    const algumGerando = gerandoLote || linhas.some(emAndamento);
    const linhaSelecionada =
        linhas.find((linha) => linha.id === selecionadaId) ?? linhas[0] ?? null;
    const indiceSelecionado = linhaSelecionada
        ? linhas.indexOf(linhaSelecionada)
        : -1;
    const posicao = indiceSelecionado + 1;
    const resumo = progresso(linhas);

    /** Gera uma linha (uma requisição, com retry de 429) e devolve o arquivo, ou null se não saiu. */
    const executar = async (
        id: number,
        sinal: AbortSignal | undefined,
    ): Promise<ArquivoGerado | null> => {
        const linha = linhasRef.current.find((atual) => atual.id === id);

        if (!linha || geracoesEmAndamento.current.has(id)) {
            return null;
        }

        geracoesEmAndamento.current.add(id);
        revogar(linha);
        atualizar((atuais) => marcarGerando(atuais, id));

        try {
            const arquivo = await gerarCertificado(
                store.url({ modelo: modelo.id }),
                linha.valores,
                {
                    nomePadrao: `${modelo.title}.png`,
                    signal: sinal,
                    aoEsperar: () => {
                        if (montado.current) {
                            atualizar((atuais) =>
                                mudarStatus(atuais, id, 'aguardando'),
                            );
                        }
                    },
                    aoRetomar: () => {
                        if (montado.current) {
                            atualizar((atuais) =>
                                mudarStatus(atuais, id, 'gerando'),
                            );
                        }
                    },
                },
            );

            // Se a página saiu do ar enquanto gerava, não cria a URL (ela nunca seria revogada).
            if (!montado.current) {
                return null;
            }

            const pronto: ArquivoGerado = {
                url: URL.createObjectURL(arquivo.blob),
                nome: arquivo.nome,
                tipo: arquivo.tipo,
                blob: arquivo.blob,
            };

            atualizar((atuais) => marcarPronto(atuais, id, pronto));
            setGeradosNaSessao((total) => total + 1);

            return pronto;
        } catch (erro) {
            if (montado.current) {
                // Cancelado pelo "Parar": a linha volta ao estado anterior, sem mensagem de erro.
                atualizar((atuais) =>
                    ehCancelamento(erro)
                        ? mudarStatus(atuais, id, 'ocioso')
                        : marcarErro(atuais, id, mensagemDoErro(erro)),
                );
            }

            return null;
        } finally {
            geracoesEmAndamento.current.delete(id);
        }
    };

    const gerar = async (id: number) => {
        setSelecionadaId(id);

        const arquivo = await executar(id, controle.current?.signal);

        if (arquivo) {
            baixarArquivo(arquivo.url, arquivo.nome);
        }
    };

    const gerarTodos = async () => {
        const mestre = controle.current;
        const ids = linhasParaGerarTodas(linhasRef.current).map(
            (linha) => linha.id,
        );

        if (!mestre || ids.length === 0 || controleDoLote.current) {
            return;
        }

        const lote = new AbortController();
        const sairDaPagina = () => lote.abort();

        controleDoLote.current = lote;
        mestre.signal.addEventListener('abort', sairDaPagina, { once: true });
        setGerandoLote(true);
        atualizar((atuais) =>
            ids.reduce(
                (proximas, id) => mudarStatus(proximas, id, 'na-fila'),
                atuais,
            ),
        );

        try {
            for (const id of ids) {
                if (lote.signal.aborted) {
                    break;
                }

                const linha = linhasRef.current.find(
                    (atual) => atual.id === id,
                );

                // Removida da tabela depois de entrar na fila: simplesmente sai do lote.
                if (!linha) {
                    continue;
                }

                // Esvaziada depois de entrar na fila: volta a ocioso, sem gerar.
                if (!linhaPreenchida(linha)) {
                    atualizar((atuais) => mudarStatus(atuais, id, 'ocioso'));
                    continue;
                }

                await executar(id, lote.signal);
            }
        } finally {
            mestre.signal.removeEventListener('abort', sairDaPagina);
            controleDoLote.current = null;

            if (montado.current) {
                atualizar((atuais) =>
                    atuais.map((linha) =>
                        linha.status === 'na-fila'
                            ? { ...linha, status: 'ocioso' }
                            : linha,
                    ),
                );
                setGerandoLote(false);
            }
        }
    };

    const pararLote = () => {
        controleDoLote.current?.abort();
    };

    const baixarProntos = async () => {
        const arquivos = linhasRef.current.flatMap((linha) =>
            linha.status === 'pronto' && linha.arquivo ? [linha.arquivo] : [],
        );

        if (arquivos.length === 0) {
            return;
        }

        try {
            const zip = await zipar(
                arquivos.map((arquivo) => ({
                    nome: arquivo.nome,
                    blob: arquivo.blob,
                })),
            );
            const url = URL.createObjectURL(zip);

            baixarArquivo(url, nomeDoZip(modelo.title));
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch {
            toast.error(
                'Não foi possível montar o arquivo .zip. Tente de novo.',
            );
        }
    };

    const editar = (id: number, nome: string, valor: string) => {
        revogar(linhasRef.current.find((linha) => linha.id === id));
        atualizar((atuais) => editarCelula(atuais, id, nome, valor));
    };

    const adicionarLinha = () => {
        const id = proximoId.current++;

        atualizar((atuais) => [...atuais, novaLinha(id, campos)]);
        setSelecionadaId(id);
        linhaParaFocar.current = id;
    };

    // Depois de "Adicionar linha", o foco vai para o primeiro campo do registro novo (tabela ou celular).
    useEffect(() => {
        const id = linhaParaFocar.current;

        if (id === null) {
            return;
        }

        linhaParaFocar.current = null;
        document
            .querySelector<HTMLInputElement>(`[data-linha-id="${id}"] input`)
            ?.focus();
    }, [linhas]);

    const remover = (id: number) => {
        const atuais = linhasRef.current;
        const indice = atuais.findIndex((linha) => linha.id === id);
        const proximas = removerLinha(atuais, id);

        revogar(atuais[indice]);
        atualizar(() => proximas);

        // Tirou a linha selecionada: a selação vai para a vizinha (mesma posição, ou a anterior se era a última).
        if (linhaSelecionada?.id === id) {
            const vizinha = proximas[Math.min(indice, proximas.length - 1)];

            setSelecionadaId(vizinha?.id ?? null);
        }
    };

    const baixar = (id: number) => {
        const arquivo = linhasRef.current.find(
            (linha) => linha.id === id,
        )?.arquivo;

        if (arquivo) {
            baixarArquivo(arquivo.url, arquivo.nome);
        }
    };

    const limpar = () => {
        if (algumGerando) {
            return;
        }

        urlsDasLinhas(linhasRef.current).forEach((url) =>
            URL.revokeObjectURL(url),
        );

        const id = proximoId.current++;

        atualizar(() => [novaLinha(id, campos)]);
        setSelecionadaId(id);
    };

    const aplicarImportacao = (
        importadas: Record<string, string>[],
        truncada: boolean,
    ) => {
        urlsDasLinhas(linhasRef.current).forEach((url) =>
            URL.revokeObjectURL(url),
        );
        const primeiro = proximoId.current;
        proximoId.current += importadas.length;
        atualizar(() => substituirLinhas(importadas, primeiro));
        setSelecionadaId(primeiro);
        toast.success(
            `${importadas.length} ${importadas.length === 1 ? 'linha importada' : 'linhas importadas'}.`,
        );

        if (truncada) {
            toast.warning(
                `Foram importadas apenas as primeiras ${LIMITE_DE_LINHAS} linhas.`,
            );
        }
    };

    const importar = async (arquivo: File) => {
        if (algumGerando) {
            return;
        }

        setLendoPlanilha(true);

        try {
            // Lê o arquivo antes de qualquer confirmação, para validar o conteúdo primeiro.
            const conteudo = await arquivo.arrayBuffer();
            const faltando = cabecalhosFaltando(conteudo, campos);

            if (faltando.length > 0) {
                toast.error(
                    `A planilha não tem as colunas: ${faltando.join(', ')}.`,
                );

                return;
            }

            const leitura = lerPlanilhaComAviso(conteudo, campos);

            if (leitura.linhas.length === 0) {
                toast.error('A planilha não tem linhas de dados.');

                return;
            }

            if (tabelaTemDados(linhasRef.current)) {
                setImportando(false);
                setSubstituicaoPendente(leitura);

                return;
            }

            aplicarImportacao(leitura.linhas, leitura.truncada);
            setImportando(false);
        } catch {
            toast.error(
                'Não foi possível ler a planilha. Confira se é um arquivo .xlsx ou .xls válido.',
            );
        } finally {
            setLendoPlanilha(false);
        }
    };

    const confirmarSubstituicao = () => {
        if (substituicaoPendente) {
            aplicarImportacao(
                substituicaoPendente.linhas,
                substituicaoPendente.truncada,
            );
        }

        setSubstituicaoPendente(null);
    };

    const baixarPlanilhaModelo = () => {
        const bytes = criarPlanilhaModelo(campos);
        const url = URL.createObjectURL(
            new Blob([bytes], {
                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            }),
        );

        baixarArquivo(url, nomeDaPlanilhaModelo(modelo.title));
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    };

    const irParaIndice = (indice: number) => {
        const alvo = linhas[indice];

        if (alvo) {
            setSelecionadaId(alvo.id);
        }
    };

    return (
        <div className="flex flex-col gap-4">
            {!celular && (
                <CabecalhoDaPagina
                    modelo={modelo.title}
                    titulo={`Gerar · ${modelo.title}`}
                    acoes={
                        <>
                            <Button
                                variant="secondary"
                                onClick={baixarPlanilhaModelo}
                            >
                                <Download />
                                Planilha em branco
                            </Button>
                            <Button
                                variant="secondary"
                                disabled={algumGerando}
                                onClick={() => setImportando(true)}
                            >
                                <FileUp />
                                Importar planilha
                            </Button>
                            <Button
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                disabled={algumGerando}
                                onClick={limpar}
                            >
                                <Trash2 />
                                Limpar
                            </Button>
                        </>
                    }
                />
            )}

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

            {!celular && (
                <div className="grid grid-cols-[minmax(0,1fr)_340px] items-start gap-6">
                    <section
                        aria-label="Registros"
                        className="min-w-0 overflow-hidden rounded-2xl border bg-card"
                    >
                        <div className="overflow-x-auto">
                            <TabelaDeGeracao
                                campos={campos}
                                linhas={linhas}
                                selecionadaId={linhaSelecionada?.id ?? null}
                                geracaoBloqueada={gerandoLote}
                                aoSelecionar={setSelecionadaId}
                                aoEditar={editar}
                                aoGerar={(id) => void gerar(id)}
                                aoBaixar={baixar}
                                aoRemover={remover}
                                aoAdicionar={adicionarLinha}
                            />
                        </div>
                        <BarraDeLote
                            prontas={resumo.prontas}
                            preenchidas={resumo.preenchidas}
                            percentual={resumo.percentual}
                            gerandoLote={gerandoLote}
                            temParaGerar={
                                linhasParaGerarTodas(linhas).length > 0
                            }
                            aoGerarTodos={() => void gerarTodos()}
                            aoParar={pararLote}
                            aoBaixarZip={() => void baixarProntos()}
                        />
                    </section>

                    <PreviaDaLinha
                        modelo={modelo}
                        campos={campos}
                        linha={linhaSelecionada}
                        posicao={posicao}
                    />
                </div>
            )}

            {celular && (
                <VisaoCelular
                    modelo={modelo}
                    campos={campos}
                    linha={linhaSelecionada}
                    posicao={posicao}
                    total={linhas.length}
                    geradosNaSessao={geradosNaSessao}
                    algumGerando={algumGerando}
                    geracaoBloqueada={gerandoLote}
                    aoEditar={editar}
                    aoAnterior={() => irParaIndice(indiceSelecionado - 1)}
                    aoProxima={() => irParaIndice(indiceSelecionado + 1)}
                    aoAdicionar={adicionarLinha}
                    aoGerarEBaixar={(id) => void gerar(id)}
                    aoBaixar={baixar}
                    aoImportar={() => setImportando(true)}
                    aoLimpar={limpar}
                />
            )}

            <DialogoDeImportacao
                aberto={importando}
                ocupado={lendoPlanilha}
                aoFechar={() => setImportando(false)}
                aoEscolherArquivo={(arquivo) => void importar(arquivo)}
                aoBaixarModelo={baixarPlanilhaModelo}
            />

            <ConfirmacaoDeSubstituicao
                aberto={substituicaoPendente !== null}
                quantidadeDeLinhas={substituicaoPendente?.linhas.length ?? 0}
                aoConfirmar={confirmarSubstituicao}
                aoCancelar={() => setSubstituicaoPendente(null)}
            />
        </div>
    );
}
