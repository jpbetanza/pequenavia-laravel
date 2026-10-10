import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/CampoCertificadoController';
import BarraDoEditor, {
    type EstadoDoSalvamento,
    type ModoDeExibicao,
} from '@/components/campos/barra-do-editor';
import ListaDeCampos from '@/components/campos/lista-de-campos';
import PainelDoCampo from '@/components/campos/painel-do-campo';
import PreviewDoModelo from '@/components/campos/preview-do-modelo';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useFontesDoCatalogo } from '@/hooks/use-fontes-do-catalogo';
import {
    deslocamentoDaTecla,
    deslocarPosicao,
    moverCampo,
    reverterCampo,
    valoresDoNovoCampo,
} from '@/lib/editor';
import { enviarJson, mensagemDoErro } from '@/lib/http';
import type {
    Campo,
    GruposFontes,
    ModeloBasico,
    PesosDasFontes,
} from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    camposIniciais: Campo[];
    fontes: GruposFontes;
    pesos: PesosDasFontes;
    somenteLeitura: boolean;
};

/** Ouvintes do arraste em andamento, guardados para poderem ser removidos a qualquer momento. */
type Arraste = {
    aoMover: (movimento: PointerEvent) => void;
    aoSoltar: (movimento: PointerEvent) => void;
    aoCancelar: () => void;
};

/** Tempo sem novas setas até o deslocamento pelo teclado ser salvo. */
const ESPERA_DO_TECLADO = 400;

function digitandoEm(alvo: EventTarget | null): boolean {
    if (!(alvo instanceof HTMLElement)) {
        return false;
    }

    return (
        alvo.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(alvo.tagName)
    );
}

export default function CamposEditor({
    modelo,
    camposIniciais,
    fontes,
    pesos,
    somenteLeitura,
}: Props) {
    useFontesDoCatalogo(fontes, pesos);

    const [campos, setCampos] = useState<Campo[]>(camposIniciais);
    const [arrastando, setArrastando] = useState<string | null>(null);
    const [salvando, setSalvando] = useState(0);
    const [falhou, setFalhou] = useState(false);
    const [criando, setCriando] = useState(false);
    const [selecionadoId, setSelecionadoId] = useState<string | null>(null);
    const [modo, setModo] = useState<ModoDeExibicao>('nomes');
    const criandoRef = useRef(false);
    /** Último valor que o servidor confirmou para cada campo; é para onde a tela volta em caso de falha. */
    const confirmados = useRef(
        new Map<string, Campo>(
            camposIniciais.map((campo) => [campo.id, campo]),
        ),
    );
    const arrasteAtivo = useRef<Arraste | null>(null);
    /** Setas do teclado ainda não salvas: um único PATCH quando o campo para de se mover. */
    const deslocamentoPendente = useRef<{
        id: string;
        mudancas: Partial<Campo>;
    } | null>(null);
    const temporizadorDoTeclado = useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );
    const descarregarRef = useRef<() => void>(() => {});

    const selecionado = campos.find((campo) => campo.id === selecionadoId);

    const encerrarArraste = useCallback(() => {
        const ativo = arrasteAtivo.current;

        if (!ativo) {
            return;
        }

        window.removeEventListener('pointermove', ativo.aoMover);
        window.removeEventListener('pointerup', ativo.aoSoltar);
        window.removeEventListener('pointercancel', ativo.aoCancelar);
        arrasteAtivo.current = null;
    }, []);

    /** Aplica a mudança na tela na hora e salva; se o servidor recusar, desfaz só o que falhou. */
    const atualizar = async (id: string, mudancas: Partial<Campo>) => {
        if (!confirmados.current.has(id)) {
            // O campo foi excluído enquanto esta mudança esperava (ex.: cor pendente ao excluir).
            return;
        }

        setCampos((atuais) =>
            atuais.map((campo) =>
                campo.id === id ? { ...campo, ...mudancas } : campo,
            ),
        );
        setSalvando((n) => n + 1);

        try {
            const salvo = await enviarJson<Campo>(
                'PATCH',
                update.url({ modelo: modelo.id, campo: id }),
                mudancas,
            );

            if (salvo) {
                confirmados.current.set(id, salvo);
            }

            setFalhou(false);
        } catch (erro) {
            toast.error(mensagemDoErro(erro));
            setFalhou(true);

            const confirmado = confirmados.current.get(id);

            if (confirmado) {
                setCampos((atuais) =>
                    atuais.map((campo) =>
                        campo.id === id
                            ? reverterCampo(campo, mudancas, confirmado)
                            : campo,
                    ),
                );
            }
        } finally {
            setSalvando((n) => n - 1);
        }
    };

    /** Salva na hora o deslocamento do teclado que ainda estava esperando a pausa. */
    const descarregarDeslocamento = () => {
        if (temporizadorDoTeclado.current) {
            clearTimeout(temporizadorDoTeclado.current);
            temporizadorDoTeclado.current = null;
        }

        const pendente = deslocamentoPendente.current;
        deslocamentoPendente.current = null;

        if (pendente) {
            void atualizar(pendente.id, pendente.mudancas);
        }
    };

    useEffect(() => {
        descarregarRef.current = descarregarDeslocamento;
    });

    useEffect(
        () => () => {
            encerrarArraste();
            descarregarRef.current();
        },
        [encerrarArraste],
    );

    const selecionar = (id: string) => {
        if (id !== selecionadoId) {
            descarregarDeslocamento();
        }

        setSelecionadoId(id);
    };

    const criar = async () => {
        if (criandoRef.current) {
            return;
        }

        criandoRef.current = true;
        setCriando(true);

        try {
            const criado = await enviarJson<Campo>(
                'POST',
                store.url({ modelo: modelo.id }),
                valoresDoNovoCampo(campos.at(-1)),
            );

            if (criado) {
                confirmados.current.set(criado.id, criado);
                setCampos((atuais) => [...atuais, criado]);
                setSelecionadoId(criado.id);
            }

            setFalhou(false);
        } catch (erro) {
            toast.error(mensagemDoErro(erro));
            setFalhou(true);
        } finally {
            criandoRef.current = false;
            setCriando(false);
        }
    };

    const excluir = async (id: string) => {
        const indice = campos.findIndex((campo) => campo.id === id);
        const removido = campos[indice];

        if (!removido) {
            return;
        }

        if (deslocamentoPendente.current?.id === id) {
            descarregarDeslocamento();
        }

        const confirmadoAntes = confirmados.current.get(id);
        confirmados.current.delete(id);
        setCampos((atuais) => atuais.filter((campo) => campo.id !== id));
        setSelecionadoId((atual) => (atual === id ? null : atual));

        try {
            await enviarJson(
                'DELETE',
                destroy.url({ modelo: modelo.id, campo: id }),
            );

            setFalhou(false);
        } catch (erro) {
            toast.error(mensagemDoErro(erro));
            setFalhou(true);

            if (confirmadoAntes) {
                confirmados.current.set(id, confirmadoAntes);
            }

            setCampos((atuais) => {
                if (atuais.some((campo) => campo.id === id)) {
                    return atuais;
                }

                const restaurados = [...atuais];
                restaurados.splice(
                    Math.min(indice, restaurados.length),
                    0,
                    removido,
                );

                return restaurados;
            });
        }
    };

    const iniciarArraste = (
        evento: React.PointerEvent,
        campo: Campo,
        escala: number,
    ) => {
        evento.preventDefault();
        encerrarArraste();
        descarregarDeslocamento();

        const inicio = { mouseX: evento.clientX, mouseY: evento.clientY };
        const original = { x: campo.x, y: campo.y };
        const novaPosicao = (movimento: PointerEvent) =>
            moverCampo(
                { x: original.x, y: original.y },
                {
                    dx: movimento.clientX - inicio.mouseX,
                    dy: movimento.clientY - inicio.mouseY,
                },
                escala,
                campo.centralizado,
            );

        const aoMover = (movimento: PointerEvent) => {
            const posicao = novaPosicao(movimento);

            setCampos((atuais) =>
                atuais.map((atual) =>
                    atual.id === campo.id ? { ...atual, ...posicao } : atual,
                ),
            );
        };

        const aoSoltar = (movimento: PointerEvent) => {
            encerrarArraste();
            setArrastando(null);

            const posicao = novaPosicao(movimento);
            const mudou =
                posicao.y !== original.y ||
                (posicao.x !== undefined && posicao.x !== original.x);

            if (mudou) {
                void atualizar(campo.id, posicao);
            }
        };

        const aoCancelar = () => {
            encerrarArraste();
            setArrastando(null);
            setCampos((atuais) =>
                atuais.map((atual) =>
                    atual.id === campo.id
                        ? { ...atual, x: original.x, y: original.y }
                        : atual,
                ),
            );
        };

        arrasteAtivo.current = { aoMover, aoSoltar, aoCancelar };
        setArrastando(campo.id);

        window.addEventListener('pointermove', aoMover);
        window.addEventListener('pointerup', aoSoltar);
        window.addEventListener('pointercancel', aoCancelar);
    };

    /** Setas movem o campo selecionado; a tela muda na hora e o servidor recebe um único PATCH na pausa. */
    const moverPeloTeclado = (evento: React.KeyboardEvent<HTMLDivElement>) => {
        if (!selecionado || digitandoEm(evento.target)) {
            return;
        }

        const deslocamento = deslocamentoDaTecla(evento.key, evento.shiftKey);

        if (!deslocamento) {
            return;
        }

        evento.preventDefault();

        const pendente =
            deslocamentoPendente.current?.id === selecionado.id
                ? deslocamentoPendente.current.mudancas
                : {};
        const base = { ...selecionado, ...pendente };
        const posicao = deslocarPosicao(
            { x: base.x, y: base.y },
            base.centralizado,
            deslocamento,
        );

        if (posicao.y === base.y && (posicao.x ?? base.x) === base.x) {
            return;
        }

        deslocamentoPendente.current = {
            id: selecionado.id,
            mudancas: { ...pendente, ...posicao },
        };

        setCampos((atuais) =>
            atuais.map((campo) =>
                campo.id === selecionado.id ? { ...campo, ...posicao } : campo,
            ),
        );

        if (temporizadorDoTeclado.current) {
            clearTimeout(temporizadorDoTeclado.current);
        }

        temporizadorDoTeclado.current = setTimeout(
            () => descarregarRef.current(),
            ESPERA_DO_TECLADO,
        );
    };

    const estado: EstadoDoSalvamento =
        salvando > 0 ? 'salvando' : falhou ? 'erro' : 'salvo';

    return (
        <div className="flex flex-col gap-6">
            <BarraDoEditor
                modelo={modelo}
                temCampos={campos.length > 0}
                modo={modo}
                aoMudarModo={setModo}
                estado={somenteLeitura ? null : estado}
            />

            {somenteLeitura && (
                <Alert>
                    <AlertDescription>
                        Você pode ver os campos deste modelo, mas não editá-los.
                    </AlertDescription>
                </Alert>
            )}

            <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-[260px_minmax(0,1fr)_300px]">
                <ListaDeCampos
                    campos={campos}
                    selecionadoId={selecionadoId}
                    somenteLeitura={somenteLeitura}
                    criando={criando}
                    aoSelecionar={selecionar}
                    aoCriar={() => void criar()}
                />

                <PreviewDoModelo
                    modelo={modelo}
                    campos={campos}
                    somenteLeitura={somenteLeitura}
                    arrastando={arrastando}
                    selecionadoId={selecionadoId}
                    modo={modo}
                    aoSelecionar={selecionar}
                    aoIniciarArraste={iniciarArraste}
                    aoTecla={moverPeloTeclado}
                />

                {selecionado ? (
                    <PainelDoCampo
                        key={selecionado.id}
                        campo={selecionado}
                        fontes={fontes}
                        pesos={pesos}
                        somenteLeitura={somenteLeitura}
                        aoAtualizar={(id, mudancas) =>
                            void atualizar(id, mudancas)
                        }
                        aoExcluir={(id) => void excluir(id)}
                    />
                ) : (
                    <aside className="rounded-xl border bg-card p-4 text-sm text-muted-foreground lg:self-start">
                        Selecione um campo na imagem ou na lista.
                    </aside>
                )}
            </div>
        </div>
    );
}
